<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;

final class ProductVersionUpdater
{
    private const UPDATE_SCANNER_REPORT_META_KEY = 'wp_shop_pm_update_scan_report_v1';
    private const VERSIONLESS_DISPLAY_PLACEHOLDER = '—';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function load(int $productId): ProductUpdateSnapshot
    {
        $this->assertProduct($productId);

        $status = (string) ($this->call)(
            'get_post_status',
            $productId
        );
        $title = (string) ($this->call)(
            'get_post_field',
            'post_title',
            $productId
        );
        $version = $this->normalizeStoredVersion(
            (string) ($this->call)(
                'get_post_meta',
                $productId,
                'attr_version_value',
                true
            )
        );
        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $itemId = (int) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_item_id',
            true
        );

        if ($itemId <= 0) {
            $itemId = $this->itemIdFromSalesPage($salesPage);
        }

        $sourceUpdateDate = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_update_date',
            true
        ));

        if ($sourceUpdateDate === '') {
            $postDate = (string) ($this->call)(
                'get_post_field',
                'post_date',
                $productId
            );
            $sourceUpdateDate = substr($postDate, 0, 10);
        }

        $sku = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_sku',
            true
        ));
        $downloadUrl = $this->downloadUrl($productId);

        return new ProductUpdateSnapshot(
            $productId,
            $status !== '' ? $status : 'unknown',
            $title,
            $this->baseTitle($title, $version),
            $itemId,
            $version,
            $sourceUpdateDate,
            $salesPage,
            $sku,
            $downloadUrl
        );
    }

    public function preflight(ProductUpdateData $data): ProductUpdateResult
    {
        $prepared = $this->prepare($data);

        if ($prepared instanceof ProductUpdateResult) {
            return $prepared;
        }

        [$preparedData, $logs] = $prepared;
        $logs[] = 'NO PRODUCT WRITTEN = YES';
        $logs[] = 'PREFLIGHT UPDATE = READY';
        $logs[] = 'TITLE = ' . $preparedData->title();
        $logs[] = 'SKU = AVAILABLE / SELF: '
            . $preparedData->skuFilename;

        return new ProductUpdateResult(true, $logs);
    }

    public function update(ProductUpdateData $data): ProductUpdateResult
    {
        $prepared = $this->prepare($data);

        if ($prepared instanceof ProductUpdateResult) {
            return $prepared;
        }

        [$preparedData, $logs] = $prepared;
        $productType = CatalogProductType::infer(
            $preparedData->baseTitle,
            $preparedData->salesPage
        );
        $displayVersion = $preparedData->version;

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            && trim($displayVersion) === ''
        ) {
            $displayVersion = self::VERSIONLESS_DISPLAY_PLACEHOLDER;
        }

        $result = ($this->call)(
            'wp_update_post',
            [
                'ID' => $preparedData->productId,
                'post_title' => $preparedData->title(),
            ],
            true
        );

        if (($this->call)('is_wp_error', $result)) {
            return new ProductUpdateResult(
                false,
                array_merge(
                    $logs,
                    [
                        'STOP: PRODUCT NOT UPDATED.',
                        'ERROR MESSAGE: ' . $this->errorMessage($result),
                    ]
                )
            );
        }

        $this->updateMeta(
            $preparedData->productId,
            '_sku',
            $preparedData->skuFilename
        );
        $this->updateMeta(
            $preparedData->productId,
            'attr_version_value',
            $displayVersion
        );
        $this->updateMeta(
            $preparedData->productId,
            '_attr_version_value',
            'field_68d531d09ce86'
        );
        $this->updateMeta(
            $preparedData->productId,
            '_wp_shop_source_update_date',
            $preparedData->sourceUpdateDate
        );
        $this->updateMeta(
            $preparedData->productId,
            '_wp_shop_source_item_id',
            (string) $preparedData->itemId
        );

        $downloadId = md5($preparedData->downloadUrl);
        $this->updateMeta(
            $preparedData->productId,
            '_downloadable_files',
            [
                $downloadId => [
                    'name' => $preparedData->skuFilename,
                    'file' => $preparedData->downloadUrl,
                ],
            ]
        );

        $logs[] = 'PRODUCT UPDATE = READY';
        $logs[] = 'PRODUCT ID = ' . $preparedData->productId;
        $logs[] = 'VERSION = ' . (
            $preparedData->version !== ''
                ? $preparedData->version
                : '[not published]'
        );
        $logs[] = 'SOURCE UPDATE DATE = '
            . $preparedData->sourceUpdateDate;
        $logs[] = 'TITLE = ' . $preparedData->title();
        $logs[] = 'SKU = ' . $preparedData->skuFilename;
        $logs[] = 'DOWNLOAD FILE = UPDATED';
        $logs[] = 'PUBLICATION DATE / STATUS = PRESERVED';
        $logs[] = 'RU/EN CONTENT = PRESERVED';
        $logs[] = 'TAGS / ATTRIBUTES / LABELS = PRESERVED';
        $logs[] = 'attr_update_value = SKIPPED';
        $logs[] = $this->markScannerReportDone($preparedData->productId)
            ? 'UPDATE SCANNER REPORT = DONE'
            : 'UPDATE SCANNER REPORT = NO MATCH';

        return new ProductUpdateResult(true, $logs);
    }

    /**
     * @return array{ProductUpdateData, list<string>}|ProductUpdateResult
     */
    private function prepare(
        ProductUpdateData $data
    ): array|ProductUpdateResult {
        $logs = ['UPDATE REQUEST = RECEIVED'];
        $errors = [];
        $productType = CatalogProductType::infer(
            $data->baseTitle,
            $data->salesPage
        );

        try {
            $this->assertProduct($data->productId);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }

        if ($errors === []) {
            $this->assertFreshForm($data, $errors);
        }

        if (trim($data->baseTitle) === '') {
            $errors[] = 'Base title is required.';
        }

        if ($data->itemId <= 0) {
            $errors[] = 'Envato Item ID is required.';
        }

        if (
            trim($data->version) === ''
            && $productType !== CatalogProductType::TEMPLATE_KIT
        ) {
            $errors[] = 'New Version is required for themes and plugins.';
        }

        if (! $this->validDate($data->sourceUpdateDate)) {
            $errors[] = 'Official update date must be YYYY-MM-DD.';
        }

        if (trim($data->salesPage) === '') {
            $errors[] = 'Sales Page is required.';
        }

        if (trim($data->downloadUrl) === '') {
            $errors[] = 'New Download URL is required before product update.';
        }

        try {
            $canonicalSku = ProductSkuFilename::synchronize(
                $data->currentSku,
                $data->itemId,
                $data->salesPage,
                $data->version
            );
        } catch (InvalidArgumentException $exception) {
            $canonicalSku = '';
            $errors[] = $exception->getMessage();
        }

        if ($canonicalSku !== '') {
            $ownerId = (int) ($this->call)(
                'wc_get_product_id_by_sku',
                $canonicalSku
            );

            if (
                $ownerId > 0
                && $ownerId !== $data->productId
            ) {
                $errors[] = 'SKU belongs to another product: '
                    . $ownerId . '.';
            }
        }

        if ($errors !== []) {
            return new ProductUpdateResult(
                false,
                array_merge(
                    $logs,
                    ['STOP: PRODUCT NOT UPDATED.'],
                    $errors
                )
            );
        }

        $currentVersion = $this->normalizeStoredVersion(
            $data->currentVersion
        );
        $logs[] = 'PRODUCT ID = ' . $data->productId;
        $logs[] = 'CURRENT VERSION = ' . (
            $currentVersion !== ''
                ? $currentVersion
                : '[empty]'
        );
        $logs[] = $data->version !== ''
            ? 'NEW VERSION = SOURCE OF TRUTH: ' . $data->version
            : 'NEW VERSION = NOT PUBLISHED; TEMPLATE KIT DATE/FILE MODE';

        if ($canonicalSku !== $data->currentSku) {
            $logs[] = 'SKU AUTO-SYNC: '
                . ($data->currentSku !== ''
                    ? $data->currentSku
                    : '[empty]')
                . ' -> '
                . $canonicalSku;
        } else {
            $logs[] = 'SKU / VERSION = MATCH';
        }

        return [
            $data->withSkuFilename($canonicalSku),
            $logs,
        ];
    }

    /**
     * @param list<string> $errors
     */
    private function assertFreshForm(
        ProductUpdateData $data,
        array &$errors
    ): void {
        $liveVersion = $this->normalizeStoredVersion(
            (string) ($this->call)(
                'get_post_meta',
                $data->productId,
                'attr_version_value',
                true
            )
        );
        $liveSku = trim((string) ($this->call)(
            'get_post_meta',
            $data->productId,
            '_sku',
            true
        ));
        $formVersion = $this->normalizeStoredVersion(
            $data->currentVersion
        );
        $formSku = trim($data->currentSku);

        if ($liveVersion !== $formVersion) {
            $errors[] = 'STALE FORM: Current Version changed from '
                . ($formVersion !== '' ? $formVersion : '[empty]')
                . ' to '
                . ($liveVersion !== '' ? $liveVersion : '[empty]')
                . '. Reload product before continuing.';
        }

        if ($liveSku !== $formSku) {
            $errors[] = 'STALE FORM: Current SKU changed. Reload product before continuing.';
        }
    }

    private function normalizeStoredVersion(string $version): string
    {
        $version = trim($version);

        return $version === self::VERSIONLESS_DISPLAY_PLACEHOLDER
            ? ''
            : $version;
    }

    private function assertProduct(int $productId): void
    {
        if ($productId <= 0) {
            throw new RuntimeException('Product ID must be positive.');
        }

        $postType = ($this->call)(
            'get_post_type',
            $productId
        );

        if ($postType !== 'product') {
            throw new RuntimeException(
                'Product ID does not reference a WooCommerce product.'
            );
        }

        $status = (string) ($this->call)(
            'get_post_status',
            $productId
        );

        if ($status === 'trash') {
            throw new RuntimeException(
                'Product is in Trash and cannot be updated.'
            );
        }
    }

    private function validDate(string $date): bool
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        return $parsed instanceof DateTimeImmutable
            && $parsed->format('Y-m-d') === $date;
    }

    private function itemIdFromSalesPage(string $salesPage): int
    {
        $path = parse_url(trim($salesPage), PHP_URL_PATH);

        if (! is_string($path)) {
            return 0;
        }

        if (
            preg_match('~/item/[^/]+/(\d+)/?$~', $path, $matches)
            !== 1
        ) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function baseTitle(
        string $title,
        string $version
    ): string {
        $title = trim($title);
        $version = trim($version);

        if ($version === '') {
            return $title;
        }

        $suffix = ' ' . $version;

        if (str_ends_with($title, $suffix)) {
            return trim(substr($title, 0, -strlen($suffix)));
        }

        return $title;
    }

    private function downloadUrl(int $productId): string
    {
        $files = ($this->call)(
            'get_post_meta',
            $productId,
            '_downloadable_files',
            true
        );

        if (! is_array($files) || $files === []) {
            return '';
        }

        $first = reset($files);

        if (! is_array($first)) {
            return '';
        }

        $file = $first['file'] ?? '';

        return is_string($file) ? $file : '';
    }

    private function updateMeta(
        int $productId,
        string $key,
        mixed $value
    ): void {
        ($this->call)(
            'update_post_meta',
            $productId,
            $key,
            $value
        );
    }

    private function markScannerReportDone(int $productId): bool
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId <= 0) {
            return false;
        }

        $report = ($this->call)(
            'get_user_meta',
            $userId,
            self::UPDATE_SCANNER_REPORT_META_KEY,
            true
        );

        if (! is_array($report)) {
            return false;
        }

        $attention = $report['attention'] ?? null;

        if (! is_array($attention) || ! isset($attention[$productId])) {
            return false;
        }

        $seen = $report['seen'] ?? [];
        $errors = $report['errors'] ?? [];

        if (! is_array($seen)) {
            $seen = [];
        }

        if (! is_array($errors)) {
            $errors = [];
        }

        unset($attention[$productId], $errors[$productId]);
        $seen[$productId] = 'DONE';
        $report['attention'] = $attention;
        $report['errors'] = $errors;
        $report['seen'] = $seen;
        $report['updated_at'] = (string) ($this->call)(
            'current_time',
            'mysql'
        );

        ($this->call)(
            'update_user_meta',
            $userId,
            self::UPDATE_SCANNER_REPORT_META_KEY,
            $report
        );

        return true;
    }

    private function errorMessage(mixed $error): string
    {
        if (
            is_object($error)
            && method_exists($error, 'get_error_message')
        ) {
            $message = $error->get_error_message();

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'WordPress product update failed.';
    }
}
