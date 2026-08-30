<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductEditorialMigrationPage implements SubmenuPageInterface
{
    private const EN_PACK_VERSION = '1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductEditorialMigrationService $migration,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-editorial-migration';
    }

    public function title(): string
    {
        return 'Editorial Migration';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $search = trim($this->request('editorial_search'));
        $page = max(1, (int) $this->request('editorial_page', '1'));
        $perPage = $this->perPage(
            (int) $this->request('editorial_per_page', '25')
        );
        $previewId = max(0, (int) $this->request('preview_id'));
        $logs = [];
        $success = null;
        $packCsv = '';

        try {
            $applyId = $this->postedInt('editorial_apply_id');
            $restoreId = $this->postedInt('editorial_restore_id');
            $action = $this->posted('wp_shop_pm_editorial_action');

            if ($applyId > 0) {
                $this->checkNonce();
                $logs = $this->migration->apply($applyId);
                $success = true;
                $previewId = $applyId;
            } elseif ($restoreId > 0) {
                $this->checkNonce();
                $logs = $this->migration->restore($restoreId);
                $success = true;
                $previewId = $restoreId;
            } elseif ($action === 'apply_selected') {
                $this->checkNonce();
                $selected = $this->selectedIds();
                $this->assertSelectedLimit($selected, 'Apply selected');

                foreach ($selected as $productId) {
                    $logs = array_merge(
                        $logs,
                        ['--- PRODUCT ' . $productId . ' ---'],
                        $this->migration->apply($productId)
                    );
                }

                $success = true;
            } elseif ($action === 'prepare_en_pack') {
                $this->checkNonce();
                $selected = $this->selectedIds();
                $this->assertSelectedLimit($selected, 'EN pack');
                $packCsv = $this->prepareEnPack($selected);
                $logs = [
                    'EDITORIAL EN PACK = READY',
                    'PRODUCTS = ' . count($selected),
                    'RU CONTENT = READ ONLY',
                    'EN COLUMNS = READY FOR TRANSLATION',
                ];
                $success = true;
            } elseif ($action === 'import_en_pack') {
                $this->checkNonce();
                $logs = $this->importEnPack();
                $success = true;
            }
        } catch (Throwable $exception) {
            $success = false;
            $logs = [
                'EDITORIAL MIGRATION = STOPPED',
                'ERROR TYPE = ' . $exception::class,
                'ERROR MESSAGE = ' . $exception->getMessage(),
                'NO FURTHER PRODUCTS WRITTEN = YES',
            ];
        }

        $ids = $this->productIds($search, $page, $perPage + 1);
        $hasNext = count($ids) > $perPage;
        $ids = array_slice($ids, 0, $perPage);
        $rows = [];

        foreach ($ids as $productId) {
            try {
                $rows[] = $this->migration->preview($productId);
            } catch (Throwable) {
                // Skip invalid/non-product rows defensively.
            }
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Editorial Migration</h1>';
        echo '<p>Приведение старых товаров к единому v28 editorial-стандарту. Сначала Preview, затем EN Translation Pack при необходимости, затем повторная проверка. Перед первой заменой каждого товара автоматически сохраняется исходный RU/EN/Meta backup.</p>';

        $this->renderLogs($logs, $success);

        echo '<div class="notice notice-warning" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>SAFE MODE:</strong> ZIP, SKU, Download URL, изображения, категории, теги, атрибуты и Advanced Labels не изменяются. Import EN Pack сохраняет только подготовленные EN Short/Long/Meta после проверки RU fingerprint и HTML-сегментов. TranslatePress dictionary синхронизируется отдельно через Apply для проверенных CURRENT товаров.</p>';
        echo '</div>';

        $this->renderBrowseForm($search, $perPage);
        $this->renderTable($rows, $search, $page, $perPage, $hasNext);

        if ($packCsv !== '') {
            $this->renderPackDownload($packCsv);
        }

        $this->renderImportForm($search, $page, $perPage);

        if ($previewId > 0) {
            try {
                $this->renderPreview(
                    $this->migration->preview($previewId),
                    $search,
                    $page,
                    $perPage
                );
            } catch (Throwable $exception) {
                echo '<div class="notice notice-error"><p>'
                    . $this->escape($exception->getMessage())
                    . '</p></div>';
            }
        }

        echo '</div>';
    }

    private function renderBrowseForm(string $search, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<form method="get" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="'
            . $this->escapeAttr($this->slug())
            . '">';
        echo '<input type="hidden" name="editorial_page" value="1">';
        echo '<label><strong>Поиск товара / ID</strong><br>';
        echo '<input type="search" name="editorial_search" value="'
            . $this->escapeAttr($search)
            . '" placeholder="Edubin или 4561" style="width:320px;max-width:100%;"></label>';
        echo '<label><strong>На странице</strong><br><select name="editorial_per_page">';

        foreach ([10, 25, 50] as $value) {
            echo '<option value="' . $value . '"'
                . ($perPage === $value ? ' selected' : '')
                . '>' . $value . '</option>';
        }

        echo '</select></label>';
        echo '<button class="button button-primary" type="submit">Показать товары</button>';
        echo '</form></div>';
    }

    /**
     * @param list<array{
     *   productId:int,title:string,baseTitle:string,status:string,productType:string,
     *   developer:string,sourceUpdateDate:string,ruStatus:string,enStatus:string,
     *   metaStatus:string,backupAvailable:bool,
     *   current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     *   generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * }> $rows
     */
    private function renderTable(
        array $rows,
        string $search,
        int $page,
        int $perPage,
        bool $hasNext
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<h2 style="margin-top:0;">Catalog Editorial Queue</h2>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="editorial_search" value="'
            . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="'
            . $this->escapeAttr((string) $page) . '">';
        echo '<input type="hidden" name="editorial_per_page" value="'
            . $this->escapeAttr((string) $perPage) . '">';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th style="width:34px;"></th><th>ID</th><th>Product</th><th>Type</th><th>RU</th><th>EN</th><th>Meta</th><th>Overall</th><th>Backup</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="10"><em>Товары не найдены.</em></td></tr>';
        }

        foreach ($rows as $row) {
            $id = $row['productId'];
            $previewUrl = $this->pageUrl([
                'preview_id' => (string) $id,
                'editorial_search' => $search,
                'editorial_page' => (string) $page,
                'editorial_per_page' => (string) $perPage,
            ]);
            $editUrl = (string) ($this->call)(
                'get_edit_post_link',
                $id,
                ''
            );
            $packEligible = $row['status'] === 'STOP'
                && $row['enStatus'] === 'REVIEW'
                && $row['productType'] !== 'unknown';

            echo '<tr>';
            echo '<td><input type="checkbox" name="editorial_selected[]" value="'
                . $this->escapeAttr((string) $id) . '"'
                . ($packEligible || $row['status'] === 'CURRENT' ? '' : ' disabled')
                . '></td>';
            echo '<td>' . $this->escape((string) $id) . '</td>';
            echo '<td><strong>' . $this->escape($row['title']) . '</strong>';

            if ($editUrl !== '') {
                echo '<br><a href="' . $this->escapeUrl($editUrl) . '">Edit product</a>';
            }

            echo '</td>';
            echo '<td>' . $this->escape($row['productType']) . '</td>';
            echo '<td>' . $this->statusBadge($row['ruStatus']) . '</td>';
            echo '<td>' . $this->statusBadge($row['enStatus']) . '</td>';
            echo '<td>' . $this->statusBadge($row['metaStatus']) . '</td>';
            echo '<td><strong>' . $this->escape($row['status']) . '</strong></td>';
            echo '<td>' . ($row['backupAvailable'] ? 'YES' : '—') . '</td>';
            echo '<td style="white-space:nowrap;">';
            echo '<a class="button button-small" href="'
                . $this->escapeUrl($previewUrl) . '">Preview</a> ';
            echo '<button class="button button-small button-primary" type="submit" name="editorial_apply_id" value="'
                . $this->escapeAttr((string) $id)
                . '" onclick="return confirm(\'Apply/sync editorial state for product #'
                . $this->escapeAttr((string) $id)
                . '?\');">Apply</button> ';

            if ($row['backupAvailable']) {
                echo '<button class="button button-small" type="submit" name="editorial_restore_id" value="'
                    . $this->escapeAttr((string) $id)
                    . '" onclick="return confirm(\'Restore editorial backup for product #'
                    . $this->escapeAttr((string) $id)
                    . '?\');">Restore</button>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-bottom:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        echo '<button type="submit" class="button" name="wp_shop_pm_editorial_action" value="prepare_en_pack">Prepare EN pack (max 25)</button>';
        echo '<button type="submit" class="button button-primary" name="wp_shop_pm_editorial_action" value="apply_selected" onclick="return confirm(\'Apply/sync all selected products?\');">Apply selected (max 25)</button>';
        echo '<span>EN pack: выбирай STOP / EN REVIEW. Apply selected: используй после импорта и проверки CURRENT для синхронизации TranslatePress.</span></p>';
        echo '</form>';
        $this->renderPagination($search, $page, $perPage, $hasNext);
        echo '</div>';
    }

    private function renderPackDownload(string $csv): void
    {
        $id = 'wp-shop-editorial-en-pack';
        $filename = 'wp-shop-editorial-en-pack-'
            . (string) ($this->call)('current_time', 'Y-m-d-His')
            . '.csv';

        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<h2 style="margin-top:0;">EN Translation Pack — READY</h2>';
        echo '<p>Скачай CSV, заполни только EN Short HTML / EN Long HTML / EN Meta и импортируй файл обратно. RU и fingerprint менять нельзя.</p>';
        echo '<textarea id="' . $id . '" readonly style="width:100%;height:160px;font-family:monospace;">'
            . $this->escape($csv)
            . '</textarea>';
        echo '<p><button type="button" class="button button-primary" id="'
            . $id . '-download">Download EN Pack CSV</button></p>';
        echo '<script>(function(){const b=document.getElementById("'
            . $id . '-download");if(!b){return;}b.addEventListener("click",function(){const t=document.getElementById("'
            . $id . '");if(!t){return;}const blob=new Blob([t.value],{type:"text/csv;charset=utf-8"});const u=URL.createObjectURL(blob);const a=document.createElement("a");a.href=u;a.download="'
            . $this->escapeAttr($filename)
            . '";document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(u);});})();</script>';
        echo '</div>';
    }

    private function renderImportForm(
        string $search,
        int $page,
        int $perPage
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<h2 style="margin-top:0;">Import translated EN Pack</h2>';
        echo '<p>Импорт сначала проверяет все строки: Product ID, тип, неизменность RU fingerprint и совместимость RU/EN HTML-сегментов. Только после полной проверки записываются подготовленные EN meta fields. RU не изменяется.</p>';
        echo '<form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="import_en_pack">';
        echo '<input type="hidden" name="editorial_search" value="'
            . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="'
            . $this->escapeAttr((string) $page) . '">';
        echo '<input type="hidden" name="editorial_per_page" value="'
            . $this->escapeAttr((string) $perPage) . '">';
        echo '<label><strong>Translated CSV</strong><br><input type="file" name="editorial_en_pack" accept=".csv,text/csv" required></label>';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Import prepared EN Short/Long/Meta for every valid row? RU content will not be changed.\');">Validate + Import EN Pack</button>';
        echo '</form></div>';
    }

    /**
     * @param array{
     *   productId:int,title:string,baseTitle:string,status:string,productType:string,
     *   developer:string,sourceUpdateDate:string,ruStatus:string,enStatus:string,
     *   metaStatus:string,backupAvailable:bool,
     *   current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     *   generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * } $preview
     */
    private function renderPreview(
        array $preview,
        string $search,
        int $page,
        int $perPage
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Preview — #'
            . $this->escape((string) $preview['productId'])
            . ' ' . $this->escape($preview['title']) . '</h2>';
        echo '<p><strong>Overall:</strong> ' . $this->escape($preview['status'])
            . ' &nbsp; <strong>Type:</strong> ' . $this->escape($preview['productType'])
            . ' &nbsp; <strong>Developer:</strong> ' . $this->escape($preview['developer'] !== '' ? $preview['developer'] : '—')
            . ' &nbsp; <strong>Source date:</strong> ' . $this->escape($preview['sourceUpdateDate'] !== '' ? $preview['sourceUpdateDate'] : '—')
            . '</p>';
        echo '<table class="widefat striped"><thead><tr><th style="width:160px;">Field</th><th>Current</th><th>Generated v28</th></tr></thead><tbody>';

        foreach (
            [
                'ruShort' => 'RU Short',
                'ruLong' => 'RU Long',
                'ruMeta' => 'RU SureRank Meta',
                'enShort' => 'EN Short',
                'enLong' => 'EN Long',
                'enMeta' => 'EN Meta',
            ] as $key => $label
        ) {
            $height = str_contains($key, 'Long') ? '180px' : '90px';
            echo '<tr><th>' . $this->escape($label) . '</th><td>';
            echo '<textarea readonly style="width:100%;height:' . $height . ';font-family:monospace;">'
                . $this->escape($preview['current'][$key])
                . '</textarea></td><td>';
            echo '<textarea readonly style="width:100%;height:' . $height . ';font-family:monospace;">'
                . $this->escape($preview['generated'][$key])
                . '</textarea></td></tr>';
        }

        echo '</tbody></table>';
        echo '<form method="post" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="editorial_search" value="'
            . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="'
            . $this->escapeAttr((string) $page) . '">';
        echo '<input type="hidden" name="editorial_per_page" value="'
            . $this->escapeAttr((string) $perPage) . '">';
        echo '<input type="hidden" name="preview_id" value="'
            . $this->escapeAttr((string) $preview['productId']) . '">';
        echo '<button class="button button-primary" type="submit" name="editorial_apply_id" value="'
            . $this->escapeAttr((string) $preview['productId'])
            . '" onclick="return confirm(\'Apply/sync editorial state for this product?\');">Apply this product</button>';

        if ($preview['backupAvailable']) {
            echo '<button class="button" type="submit" name="editorial_restore_id" value="'
                . $this->escapeAttr((string) $preview['productId'])
                . '" onclick="return confirm(\'Restore saved editorial backup?\');">Restore backup</button>';
        }

        echo '</form></div>';
    }

    /** @param list<int> $selected */
    private function prepareEnPack(array $selected): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to create EN pack stream.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $this->packHeaders(), ';', '"', '');

        foreach ($selected as $productId) {
            $preview = $this->migration->preview($productId);

            if (
                $preview['status'] !== 'STOP'
                || $preview['enStatus'] !== 'REVIEW'
                || $preview['productType'] === 'unknown'
            ) {
                fclose($stream);
                throw new RuntimeException(
                    'Product #' . $productId
                    . ' is not eligible for EN pack. Expected STOP / EN REVIEW with known type.'
                );
            }

            $current = $preview['current'];
            fputcsv(
                $stream,
                [
                    self::EN_PACK_VERSION,
                    (string) $productId,
                    $preview['title'],
                    $preview['productType'],
                    $preview['developer'],
                    $this->ruFingerprint($current),
                    $current['ruShort'],
                    $current['ruLong'],
                    $current['ruMeta'],
                    $current['enShort'],
                    $current['enLong'],
                    $current['enMeta'],
                ],
                ';',
                '"',
                ''
            );
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($csv) || $csv === '') {
            throw new RuntimeException('EN pack CSV is empty.');
        }

        return $csv;
    }

    /** @return list<string> */
    private function importEnPack(): array
    {
        $upload = $_FILES['editorial_en_pack'] ?? null;

        if (! is_array($upload)) {
            throw new RuntimeException('EN pack CSV was not uploaded.');
        }

        $error = isset($upload['error']) && is_scalar($upload['error'])
            ? (int) $upload['error']
            : UPLOAD_ERR_NO_FILE;
        $name = isset($upload['name']) && is_scalar($upload['name'])
            ? (string) $upload['name']
            : '';
        $tmpName = isset($upload['tmp_name']) && is_scalar($upload['tmp_name'])
            ? (string) $upload['tmp_name']
            : '';
        $size = isset($upload['size']) && is_scalar($upload['size'])
            ? (int) $upload['size']
            : 0;

        if ($error !== UPLOAD_ERR_OK || $tmpName === '') {
            throw new RuntimeException('EN pack upload failed with code ' . $error . '.');
        }

        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('EN pack must be between 1 byte and 8 MB.');
        }

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('EN pack must be a .csv file.');
        }

        $stream = fopen($tmpName, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open uploaded EN pack.');
        }

        try {
            $headers = fgetcsv($stream, 0, ';', '"', '');

            if (! is_array($headers)) {
                throw new RuntimeException('EN pack header is missing.');
            }

            $headers = array_map(
                static fn(mixed $value): string => is_scalar($value)
                    ? trim((string) $value)
                    : '',
                $headers
            );
            $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");

            if ($headers !== $this->packHeaders()) {
                throw new RuntimeException(
                    'EN pack header does not match the expected v1 format.'
                );
            }

            $rows = [];

            while (($csvRow = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                if ($this->csvRowEmpty($csvRow)) {
                    continue;
                }

                if (count($csvRow) !== count($headers)) {
                    throw new RuntimeException('EN pack contains a malformed CSV row.');
                }

                $combined = array_combine($headers, $csvRow);

                if (! is_array($combined)) {
                    throw new RuntimeException('EN pack row could not be mapped.');
                }

                $rows[] = $combined;

                if (count($rows) > 25) {
                    throw new RuntimeException('EN pack import is limited to 25 products per run.');
                }
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            throw new RuntimeException('EN pack contains no product rows.');
        }

        $mapBuilder = new TranslationMapBuilder();
        $prepared = [];
        $seen = [];

        foreach ($rows as $row) {
            $productId = (int) trim((string) ($row['Product ID'] ?? '0'));

            if ($productId <= 0 || isset($seen[$productId])) {
                throw new RuntimeException('EN pack contains an invalid or duplicate Product ID.');
            }

            $seen[$productId] = true;

            if (trim((string) ($row['Pack Version'] ?? '')) !== self::EN_PACK_VERSION) {
                throw new RuntimeException('Unsupported EN pack version for product #' . $productId . '.');
            }

            $preview = $this->migration->preview($productId);

            if ($preview['productType'] === 'unknown') {
                throw new RuntimeException('Product #' . $productId . ' has unknown type.');
            }

            $packedType = trim((string) ($row['Type'] ?? ''));

            if ($packedType !== $preview['productType']) {
                throw new RuntimeException(
                    'Product #' . $productId . ' type changed: pack='
                    . $packedType . ', current=' . $preview['productType'] . '.'
                );
            }

            $current = $preview['current'];
            $fingerprint = trim((string) ($row['RU Fingerprint'] ?? ''));

            if (! hash_equals($this->ruFingerprint($current), $fingerprint)) {
                throw new RuntimeException(
                    'Product #' . $productId
                    . ' RU content changed after export. Re-export the EN pack.'
                );
            }

            $enShort = trim((string) ($row['EN Short HTML'] ?? ''));
            $enLong = trim((string) ($row['EN Long HTML'] ?? ''));
            $enMeta = trim((string) ($row['EN Meta'] ?? ''));

            try {
                $mapBuilder->build(
                    $current['ruShort'],
                    $current['ruLong'],
                    $current['ruMeta'],
                    $enShort,
                    $enLong,
                    $enMeta
                );
            } catch (InvalidArgumentException $exception) {
                throw new RuntimeException(
                    'Product #' . $productId . ' translation validation failed: '
                    . $exception->getMessage(),
                    0,
                    $exception
                );
            }

            $prepared[] = [
                'productId' => $productId,
                'enShort' => $enShort,
                'enLong' => $enLong,
                'enMeta' => $enMeta,
            ];
        }

        $logs = [
            'EDITORIAL EN PACK IMPORT = VALIDATED',
            'PRODUCTS = ' . count($prepared),
            'RU CONTENT = PRESERVED',
        ];

        foreach ($prepared as $item) {
            $productId = $item['productId'];
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_en_short_description',
                (string) ($this->call)('wp_kses_post', $item['enShort'])
            );
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_en_long_description',
                (string) ($this->call)('wp_kses_post', $item['enLong'])
            );
            ($this->call)(
                'update_post_meta',
                $productId,
                '_wp_shop_en_meta_description',
                (string) ($this->call)('sanitize_textarea_field', $item['enMeta'])
            );

            $postPreview = $this->migration->preview($productId);
            $logs[] = 'PRODUCT ' . $productId
                . ' = EN PREPARED / OVERALL ' . $postPreview['status'];
        }

        $logs[] = 'TRANSLATEPRESS SYNC = NOT RUN';
        $logs[] = 'NEXT = REVIEW CURRENT, THEN APPLY SELECTED';

        return $logs;
    }

    /** @return list<string> */
    private function packHeaders(): array
    {
        return [
            'Pack Version',
            'Product ID',
            'Product',
            'Type',
            'Developer',
            'RU Fingerprint',
            'RU Short HTML',
            'RU Long HTML',
            'RU Meta',
            'EN Short HTML',
            'EN Long HTML',
            'EN Meta',
        ];
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $current
     */
    private function ruFingerprint(array $current): string
    {
        return hash(
            'sha256',
            $current['ruShort'] . "\0"
                . $current['ruLong'] . "\0"
                . $current['ruMeta']
        );
    }

    /** @param array<int,mixed> $row */
    private function csvRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param list<int> $selected */
    private function assertSelectedLimit(array $selected, string $label): void
    {
        if ($selected === []) {
            throw new RuntimeException('No products selected.');
        }

        if (count($selected) > 25) {
            throw new RuntimeException($label . ' is limited to 25 products per run.');
        }
    }

    private function renderPagination(
        string $search,
        int $page,
        int $perPage,
        bool $hasNext
    ): void {
        echo '<p style="display:flex;gap:8px;align-items:center;">';

        if ($page > 1) {
            echo '<a class="button" href="'
                . $this->escapeUrl($this->pageUrl([
                    'editorial_search' => $search,
                    'editorial_page' => (string) ($page - 1),
                    'editorial_per_page' => (string) $perPage,
                ])) . '">← Previous</a>';
        }

        echo '<strong>Page ' . $this->escape((string) $page) . '</strong>';

        if ($hasNext) {
            echo '<a class="button" href="'
                . $this->escapeUrl($this->pageUrl([
                    'editorial_search' => $search,
                    'editorial_page' => (string) ($page + 1),
                    'editorial_per_page' => (string) $perPage,
                ])) . '">Next →</a>';
        }

        echo '</p>';
    }

    /** @return list<int> */
    private function productIds(string $search, int $page, int $limit): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => $limit,
            'offset' => ($page - 1) * ($limit - 1),
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ];

        if ($search !== '') {
            if (ctype_digit($search)) {
                $args['post__in'] = [(int) $search];
                $args['orderby'] = 'post__in';
            } else {
                $args['s'] = $search;
            }
        }

        $ids = ($this->call)('get_posts', $args);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }

    /** @return list<int> */
    private function selectedIds(): array
    {
        $raw = $_POST['editorial_selected'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            if (is_scalar($value)) {
                $id = (int) $value;

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function perPage(int $value): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : 25;
    }

    private function statusBadge(string $status): string
    {
        $style = match ($status) {
            'CURRENT' => 'color:#008a20;font-weight:700;',
            'MISSING' => 'color:#b32d2e;font-weight:700;',
            default => 'color:#996800;font-weight:700;',
        };

        return '<span style="' . $style . '">' . $this->escape($status) . '</span>';
    }

    /** @param list<string> $logs */
    private function renderLogs(array $logs, ?bool $success): void
    {
        if ($logs === []) {
            return;
        }

        $class = $success === true ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . '"><pre style="white-space:pre-wrap;">'
            . $this->escape(implode("\n", $logs))
            . '</pre></div>';
    }

    /** @param array<string,string> $args */
    private function pageUrl(array $args): string
    {
        $query = array_merge(['page' => $this->slug()], $args);

        return (string) ($this->call)(
            'admin_url',
            'admin.php?' . http_build_query($query)
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_editorial_migration',
            '_wpnonce',
            true,
            true
        );
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_editorial_migration'
        );
    }

    private function posted(string $key): string
    {
        if (! isset($_POST[$key]) || ! is_scalar($_POST[$key])) {
            return '';
        }

        return trim((string) ($this->call)(
            'sanitize_text_field',
            (string) $_POST[$key]
        ));
    }

    private function postedInt(string $key): int
    {
        return (int) $this->posted($key);
    }

    private function request(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        if (! is_scalar($value)) {
            return $default;
        }

        return trim((string) ($this->call)(
            'sanitize_text_field',
            (string) $value
        ));
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }

    private function escapeAttr(string $value): string
    {
        return (string) ($this->call)('esc_attr', $value);
    }

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
