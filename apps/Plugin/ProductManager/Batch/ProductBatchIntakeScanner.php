<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveVersionInspector;

final class ProductBatchIntakeScanner
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly ProductArchiveVersionInspector $versionInspector,
        private readonly ProductArchiveIdentityInspector $identityInspector
    ) {
    }

    public function inboxDirectory(string $uploadsBaseDir): string
    {
        $uploadsBaseDir = rtrim(trim($uploadsBaseDir), '/\\');

        if ($uploadsBaseDir === '') {
            throw new RuntimeException('Uploads base directory is unavailable.');
        }

        return $uploadsBaseDir
            . DIRECTORY_SEPARATOR . 'woocommerce_uploads'
            . DIRECTORY_SEPARATOR . 'INBOX';
    }

    public function ensureInbox(string $uploadsBaseDir): string
    {
        $directory = $this->inboxDirectory($uploadsBaseDir);

        if (is_dir($directory)) {
            return $directory;
        }

        $created = (bool) ($this->call)('wp_mkdir_p', $directory);

        if (! $created && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create Product Manager INBOX directory.');
        }

        return $directory;
    }

    /**
     * @return list<string>
     */
    public function folders(string $uploadsBaseDir): array
    {
        $root = $this->ensureInbox($uploadsBaseDir);
        $entries = scandir($root);

        if ($entries === false) {
            return [];
        }

        $folders = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '_')) {
                continue;
            }

            if (is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                $folders[] = $entry;
            }
        }

        natcasesort($folders);

        return array_values($folders);
    }

    /**
     * @return list<array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }>
     */
    public function scan(string $uploadsBaseDir, string $folder): array
    {
        $root = $this->ensureInbox($uploadsBaseDir);
        $folder = $this->normalizeFolder($folder);
        $directory = $folder === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . $folder;

        if (! is_dir($directory)) {
            throw new RuntimeException('Selected INBOX folder does not exist.');
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException('Cannot read selected INBOX folder.');
        }

        $rows = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (
                ! is_file($path)
                || strtolower((string) pathinfo($entry, PATHINFO_EXTENSION)) !== 'zip'
            ) {
                continue;
            }

            $rows[] = $this->inspectArchive($path, $entry, $folder);
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => strnatcasecmp(
                $left['filename'],
                $right['filename']
            )
        );

        return $rows;
    }

    /**
     * @return array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }
     */
    private function inspectArchive(
        string $path,
        string $filename,
        string $folder
    ): array {
        $identity = $this->identityInspector->inspect($path, $filename);
        $itemId = $this->itemIdFromFilename($filename);
        $productId = $itemId > 0 ? $this->productIdByItemId($itemId) : 0;

        if ($productId <= 0 && $identity->success) {
            $productId = $this->productIdByIdentity(
                $identity->name,
                $identity->productType
            );

            if ($productId > 0 && $itemId <= 0) {
                $itemId = $this->sourceItemId($productId);
            }
        }

        $productTitle = $productId > 0
            ? trim((string) ($this->call)('get_the_title', $productId))
            : '';
        $productType = $productId > 0
            ? $this->existingProductType($productId, $productTitle)
            : ($identity->success
                ? $identity->productType
                : $this->detectNewProductType($path, $filename));
        $currentVersion = $productId > 0
            ? $this->currentVersion($productId)
            : '';
        $detectedVersion = '';
        $status = 'READY';
        $note = '';

        if ($productType === '') {
            $status = 'REVIEW';
            $note = 'PRODUCT TYPE NOT DETECTED';
        } elseif ($productType === CatalogProductType::TEMPLATE_KIT) {
            $detectedVersion = '—';
            $note = $identity->success && $productId > 0
                ? 'MATCHED BY ZIP IDENTITY: ' . $identity->name
                : 'VERSIONLESS TEMPLATE KIT';
        } elseif (
            $identity->success
            && $identity->productType === $productType
            && $identity->version !== ''
        ) {
            $detectedVersion = $identity->version;
            $note = $productId > 0 && $this->itemIdFromFilename($filename) <= 0
                ? 'MATCHED BY ZIP IDENTITY: '
                    . $identity->name
                    . ' (' . $identity->source . ')'
                : 'ZIP VERSION SOURCE = ' . $identity->source;
        } else {
            $inspection = $this->versionInspector->inspect(
                [
                    'name' => $filename,
                    'tmp_name' => $path,
                    'error' => UPLOAD_ERR_OK,
                ],
                $productType
            );

            if (! $inspection->success) {
                $status = 'REVIEW';
                $note = $inspection->logs[0] ?? 'ZIP VERSION NOT DETECTED';
            } else {
                $detectedVersion = $inspection->version;
                $note = $inspection->logs[2] ?? 'ZIP INSPECTION = READY';
            }
        }

        $action = $productId > 0
            ? 'UPDATE'
            : ($itemId > 0 ? 'CREATE' : 'REVIEW');

        if ($action === 'REVIEW' && $status === 'READY') {
            $status = 'REVIEW';
            $note = $identity->success
                ? 'NEW PRODUCT ITEM ID REQUIRED; ZIP IDENTITY = ' . $identity->name
                : 'ITEM ID NOT DETECTED FROM ZIP FILENAME';
        }

        return [
            'filename' => $filename,
            'relativePath' => ($folder !== '' ? $folder . '/' : '') . $filename,
            'itemId' => $itemId,
            'productId' => $productId,
            'productTitle' => $productTitle,
            'productType' => $productType,
            'currentVersion' => $currentVersion,
            'detectedVersion' => $detectedVersion,
            'action' => $action,
            'status' => $status,
            'note' => $note,
        ];
    }

    private function normalizeFolder(string $folder): string
    {
        $folder = trim($folder);

        if ($folder === '') {
            return '';
        }

        if (
            $folder === '.'
            || $folder === '..'
            || basename($folder) !== $folder
            || preg_match('/^[A-Za-z0-9._ -]+$/', $folder) !== 1
        ) {
            throw new RuntimeException('Unsafe INBOX folder name.');
        }

        return $folder;
    }

    private function itemIdFromFilename(string $filename): int
    {
        if (
            preg_match(
                '/(?:themeforest|codecanyon)-(\d+)-/i',
                $filename,
                $matches
            ) === 1
        ) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function productIdByItemId(int $itemId): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_key' => '_wp_shop_source_item_id',
                'meta_value' => (string) $itemId,
            ]
        );

        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        return (int) reset($ids);
    }

    private function productIdByIdentity(string $name, string $productType): int
    {
        $name = trim($name);

        if ($name === '' || $productType === '') {
            return 0;
        }

        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 10,
                'fields' => 'ids',
                's' => $name,
            ]
        );

        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        $matches = [];

        foreach ($ids as $candidateId) {
            $candidateId = (int) $candidateId;

            if ($candidateId <= 0) {
                continue;
            }

            $title = trim((string) ($this->call)(
                'get_the_title',
                $candidateId
            ));
            $candidateType = $this->existingProductType(
                $candidateId,
                $title
            );

            if ($candidateType === $productType) {
                $matches[] = $candidateId;
            }
        }

        return count($matches) === 1 ? $matches[0] : 0;
    }

    private function sourceItemId(int $productId): int
    {
        return (int) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_item_id',
            true
        );
    }

    private function existingProductType(
        int $productId,
        string $productTitle
    ): string {
        $stored = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_product_type',
            true
        ));

        if (
            in_array(
                $stored,
                [
                    CatalogProductType::THEME,
                    CatalogProductType::PLUGIN,
                    CatalogProductType::TEMPLATE_KIT,
                ],
                true
            )
        ) {
            return $stored;
        }

        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));

        return CatalogProductType::infer($productTitle, $salesPage);
    }

    private function detectNewProductType(
        string $path,
        string $filename
    ): string {
        if (str_starts_with(strtolower($filename), 'codecanyon-')) {
            return CatalogProductType::PLUGIN;
        }

        if (! str_starts_with(strtolower($filename), 'themeforest-')) {
            return '';
        }

        $themeInspection = $this->versionInspector->inspect(
            [
                'name' => $filename,
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
            ],
            CatalogProductType::THEME
        );

        return $themeInspection->success
            ? CatalogProductType::THEME
            : CatalogProductType::TEMPLATE_KIT;
    }

    private function currentVersion(int $productId): string
    {
        $version = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'attr_version_value',
            true
        ));

        return $version === '—' ? '' : $version;
    }
}
