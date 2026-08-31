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
    private const PACK_VERSION = '2';
    private const EN_TARGET_RU_FINGERPRINT_META = '_wp_shop_en_target_ru_fingerprint_v2';
    private const EN_CONTENT_FINGERPRINT_META = '_wp_shop_en_content_fingerprint_v2';

    /** @param Closure(string, mixed...): mixed $call */
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
        $perPage = $this->perPage((int) $this->request('editorial_per_page', '25'));
        $previewId = max(0, (int) $this->request('preview_id'));
        $manualId = max(0, (int) $this->request('manual_id'));
        $logs = [];
        $success = null;
        $csv = '';
        $auditCsv = '';

        try {
            $applyId = $this->postedInt('editorial_apply_id');
            $restoreId = $this->postedInt('editorial_restore_id');
            $action = $this->posted('wp_shop_pm_editorial_action');

            if ($applyId > 0) {
                $this->checkNonce();
                $logs = $this->migration->apply($applyId);
                $previewId = $applyId;
                $success = true;
            } elseif ($restoreId > 0) {
                $this->checkNonce();
                $logs = $this->migration->restore($restoreId);
                $previewId = $restoreId;
                $success = true;
            } elseif ($action === 'save_manual_draft') {
                $this->checkNonce();
                $manualId = $this->postedInt('manual_product_id');
                if ($manualId <= 0) {
                    throw new RuntimeException('Manual editor requires a valid Product ID.');
                }
                $logs = $this->migration->saveManualDraft(
                    $manualId,
                    $this->manualPostedContent()
                );
                $editor = $this->migration->manualEditor($manualId);
                $logs = array_merge($logs, $this->manualPreviewLogs($editor));
                $success = $editor['status'] === 'READY';
            } elseif ($action === 'apply_manual') {
                $this->checkNonce();
                $manualId = $this->postedInt('manual_product_id');
                if ($manualId <= 0) {
                    throw new RuntimeException('Manual editor requires a valid Product ID.');
                }
                $logs = $this->migration->applyManual($manualId);
                $previewId = $manualId;
                $success = in_array('TRANSLATEPRESS SYNC = READY', $logs, true);
            } elseif ($action === 'discard_manual') {
                $this->checkNonce();
                $manualId = $this->postedInt('manual_product_id');
                if ($manualId <= 0) {
                    throw new RuntimeException('Manual editor requires a valid Product ID.');
                }
                $logs = $this->migration->discardManualDraft($manualId);
                $success = true;
            } elseif ($action === 'apply_selected') {
                $this->checkNonce();
                $selected = $this->selectedIds();
                $this->limitSelected($selected, 'Apply selected');
                $this->assertBatchReady($selected);
                foreach ($selected as $id) {
                    $logs[] = '--- PRODUCT ' . $id . ' ---';
                    $logs = array_merge($logs, $this->migration->apply($id));
                }
                $success = true;
            } elseif ($action === 'prepare_en_pack') {
                $this->checkNonce();
                $selected = $this->selectedIds();
                $this->limitSelected($selected, 'EN pack');
                $csv = $this->preparePack($selected);
                $logs = [
                    'EDITORIAL EN PACK V2 = READY',
                    'PRODUCTS = ' . count($selected),
                    'SOURCE RU = READ ONLY',
                    'TARGET GENERATED V28 RU = EXPORTED',
                    'EN COLUMNS = READY FOR TRANSLATION',
                ];
                $success = true;
            } elseif ($action === 'prepare_audit_page') {
                $this->checkNonce();
                $auditIds = $this->productIds($search, $page, $perPage + 1);
                $auditIds = array_slice($auditIds, 0, $perPage);
                if ($auditIds === []) {
                    throw new RuntimeException('No products on this audit page.');
                }
                $auditCsv = $this->prepareAudit($auditIds);
                $logs = [
                    'CATALOG EDITORIAL AUDIT = READY',
                    'PAGE = ' . $page,
                    'PRODUCTS = ' . count($auditIds),
                    'MODE = PREVIEW ONLY / NO WRITES',
                    'OFFICIAL ENVATO LIVE ENRICHMENT = NOT REQUESTED',
                    'NEXT = DOWNLOAD CSV AND REVIEW OFFLINE',
                ];
                $success = true;
            } elseif ($action === 'import_en_pack') {
                $this->checkNonce();
                $logs = $this->importPack();
                $success = true;
            }
        } catch (Throwable $exception) {
            $logs = [
                'EDITORIAL MIGRATION = STOPPED',
                'ERROR TYPE = ' . $exception::class,
                'ERROR MESSAGE = ' . $exception->getMessage(),
                'NO FURTHER PRODUCTS WRITTEN = YES',
            ];
            $success = false;
        }

        $ids = $this->productIds($search, $page, $perPage + 1);
        $hasNext = count($ids) > $perPage;
        $ids = array_slice($ids, 0, $perPage);
        $rows = [];
        foreach ($ids as $id) {
            try {
                $rows[] = $this->migration->preview($id);
            } catch (Throwable) {
            }
        }

        echo '<div class="wrap"><h1>WP Shop Product Manager — Editorial Migration</h1>';
        echo '<p>EN Translation Pack v2 переводит целевой Generated v28 RU, а не старый Current RU.</p>';
        $this->renderLogs($logs, $success);
        echo '<div class="notice notice-warning" style="max-width:1500px;padding:10px 14px"><p>';
        echo '<strong>SAFE MODE:</strong> ZIP, SKU, Download URL, изображения, категории, теги, атрибуты и Advanced Labels не изменяются. Import v2 проверяет Source RU и Target v28 fingerprints и пишет только EN Short/Long/Meta. Apply selected сначала проверяет всю пачку и ничего не пишет, если хотя бы один товар не готов. Manual RU+EN хранится как staging draft и не меняет товар до отдельного Apply Manual.</p></div>';
        $this->renderBrowse($search, $perPage);
        $this->renderTable($rows, $search, $page, $perPage, $hasNext);
        if ($csv !== '') {
            $this->renderDownload($csv);
        }
        if ($auditCsv !== '') {
            $this->renderAuditDownload($auditCsv, $page);
        }
        $this->renderImport($search, $page, $perPage);
        $this->renderManualEditor($manualId, $search, $page, $perPage);
        if ($previewId > 0) {
            try {
                $this->renderPreview($this->migration->preview($previewId), $search, $page, $perPage);
            } catch (Throwable $exception) {
                echo '<div class="notice notice-error"><p>'
                    . $this->escape($exception->getMessage()) . '</p></div>';
            }
        }
        echo '</div>';
    }

    private function renderBrowse(string $search, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><form method="get">';
        echo '<input type="hidden" name="page" value="' . $this->escapeAttr($this->slug()) . '">';
        echo '<input type="hidden" name="editorial_page" value="1">';
        echo '<label><strong>Поиск товара / ID</strong> <input type="search" name="editorial_search" value="'
            . $this->escapeAttr($search) . '"></label> &nbsp; ';
        echo '<label><strong>На странице</strong> <select name="editorial_per_page">';
        foreach ([10, 25, 50] as $value) {
            echo '<option value="' . $value . '"' . ($perPage === $value ? ' selected' : '')
                . '>' . $value . '</option>';
        }
        echo '</select></label> <button class="button button-primary">Показать товары</button>';
        echo '</form></div>';
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderTable(array $rows, string $search, int $page, int $perPage, bool $hasNext): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><h2>Catalog Editorial Queue</h2>';
        echo '<form method="post">';
        $this->nonceField();
        $this->hiddenState($search, $page, $perPage);
        echo '<table class="widefat striped"><thead><tr><th></th><th>ID</th><th>Product</th><th>Type</th>';
        echo '<th>RU</th><th>EN</th><th>Meta</th><th>Overall</th><th>Backup</th><th>Actions</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="10"><em>Товары не найдены.</em></td></tr>';
        }
        foreach ($rows as $row) {
            $id = (int) $row['productId'];
            $eligible = $this->packEligible($row);
            $selectable = $eligible || $row['status'] === 'CURRENT';
            $previewUrl = $this->pageUrl([
                'preview_id' => (string) $id,
                'editorial_search' => $search,
                'editorial_page' => (string) $page,
                'editorial_per_page' => (string) $perPage,
            ]);
            $manualUrl = $this->pageUrl([
                'manual_id' => (string) $id,
                'editorial_search' => $search,
                'editorial_page' => (string) $page,
                'editorial_per_page' => (string) $perPage,
            ]);
            echo '<tr><td><input type="checkbox" name="editorial_selected[]" value="' . $id . '"'
                . ($selectable ? '' : ' disabled') . '></td>';
            echo '<td>' . $id . '</td><td><strong>' . $this->escape((string) $row['title']) . '</strong></td>';
            echo '<td>' . $this->escape((string) $row['productType']) . '</td>';
            echo '<td>' . $this->badge((string) $row['ruStatus']) . '</td>';
            echo '<td>' . $this->badge((string) $row['enStatus']) . '</td>';
            echo '<td>' . $this->badge((string) $row['metaStatus']) . '</td>';
            echo '<td><strong>' . $this->escape((string) $row['status']) . '</strong></td>';
            echo '<td>' . (! empty($row['backupAvailable']) ? 'YES' : '—') . '</td><td>';
            echo '<a class="button button-small" href="' . $this->escapeUrl($previewUrl) . '">Preview</a> ';
            echo '<a class="button button-small" href="' . $this->escapeUrl($manualUrl) . '">Manual RU+EN</a> ';
            echo '<button class="button button-small button-primary" name="editorial_apply_id" value="' . $id
                . '">Apply</button> ';
            if (! empty($row['backupAvailable'])) {
                echo '<button class="button button-small" name="editorial_restore_id" value="' . $id
                    . '">Restore</button>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table><p>';
        echo '<button class="button" name="wp_shop_pm_editorial_action" value="prepare_audit_page">Audit CSV — current page</button> ';
        echo '<button class="button" name="wp_shop_pm_editorial_action" value="prepare_en_pack">Prepare EN pack v2 (max 25)</button> ';
        echo '<button class="button button-primary" name="wp_shop_pm_editorial_action" value="apply_selected">Apply selected (max 25)</button> ';
        echo '<span>Audit CSV ничего не записывает и выгружает Current + Generated v28 для текущей страницы. EN pack v2: STOP / EN REVIEW или MIGRATE. Apply selected выполняется только если вся выбранная пачка прошла preflight.</span></p>';
        echo '</form>';
        $this->renderPagination($search, $page, $perPage, $hasNext);
        echo '</div>';
    }

    /** @param list<int> $ids */
    private function prepareAudit(array $ids): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to create audit CSV stream.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $this->auditHeaders(), ';', '"', '');

        foreach ($ids as $id) {
            try {
                $preview = $this->previewForAudit($id);
                $current = $preview['current'];
                $generated = $preview['generated'];
                fputcsv($stream, [
                    '1',
                    (string) $id,
                    (string) $preview['title'],
                    (string) $preview['productType'],
                    (string) $preview['developer'],
                    (string) $preview['status'],
                    (string) $preview['ruStatus'],
                    (string) $preview['enStatus'],
                    (string) $preview['metaStatus'],
                    ! empty($preview['backupAvailable']) ? 'YES' : 'NO',
                    (string) $preview['sourceUpdateDate'],
                    (string) $preview['officialStatus'],
                    (string) $preview['officialFacts'],
                    (string) $current['ruShort'],
                    (string) $current['ruLong'],
                    (string) $current['ruMeta'],
                    (string) $generated['ruShort'],
                    (string) $generated['ruLong'],
                    (string) $generated['ruMeta'],
                    (string) $current['enShort'],
                    (string) $current['enLong'],
                    (string) $current['enMeta'],
                    (string) $generated['enShort'],
                    (string) $generated['enLong'],
                    (string) $generated['enMeta'],
                    '',
                ], ';', '"', '');
            } catch (Throwable $exception) {
                fputcsv($stream, [
                    '1', (string) $id, '', '', '', 'ERROR', 'REVIEW', 'REVIEW', 'REVIEW',
                    '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                    $exception->getMessage(),
                ], ';', '"', '');
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($csv) || $csv === '') {
            throw new RuntimeException('Unable to read audit CSV stream.');
        }

        return $csv;
    }

    /** @return list<string> */
    private function auditHeaders(): array
    {
        return [
            'Audit Version', 'Product ID', 'Product', 'Type', 'Developer', 'Overall',
            'RU Status', 'EN Status', 'Meta Status', 'Backup', 'Source Update Date',
            'Official Status', 'Official Facts',
            'Current RU Short HTML', 'Current RU Long HTML', 'Current RU Meta',
            'Generated RU Short HTML', 'Generated RU Long HTML', 'Generated RU Meta',
            'Current EN Short HTML', 'Current EN Long HTML', 'Current EN Meta',
            'Generated EN Short HTML', 'Generated EN Long HTML', 'Generated EN Meta',
            'Preview Error',
        ];
    }

    /** @return array<string, mixed> */
    private function previewForAudit(int $id): array
    {
        $postSelectedHad = array_key_exists('editorial_selected', $_POST);
        $postSelected = $_POST['editorial_selected'] ?? null;
        $postApplyHad = array_key_exists('editorial_apply_id', $_POST);
        $postApply = $_POST['editorial_apply_id'] ?? null;
        $requestPreviewHad = array_key_exists('preview_id', $_REQUEST);
        $requestPreview = $_REQUEST['preview_id'] ?? null;

        unset($_POST['editorial_selected'], $_POST['editorial_apply_id'], $_REQUEST['preview_id']);

        try {
            return $this->migration->preview($id);
        } finally {
            if ($postSelectedHad) {
                $_POST['editorial_selected'] = $postSelected;
            }
            if ($postApplyHad) {
                $_POST['editorial_apply_id'] = $postApply;
            }
            if ($requestPreviewHad) {
                $_REQUEST['preview_id'] = $requestPreview;
            }
        }
    }

    private function renderAuditDownload(string $csv, int $page): void
    {
        $filename = 'wp-shop-editorial-audit-v28-page-'
            . str_pad((string) $page, 3, '0', STR_PAD_LEFT) . '-'
            . (string) ($this->call)('current_time', 'Y-m-d-His') . '.csv';
        $href = 'data:text/csv;charset=utf-8;base64,' . base64_encode($csv);
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><h2>Catalog Editorial Audit — READY</h2>';
        echo '<p>Read-only audit. Current и Generated v28 выгружены без записи в товары и без live Envato enrichment. Для финальной проверки проблемного товара открой Preview.</p>';
        echo '<a class="button button-primary" download="' . $this->escapeAttr($filename) . '" href="'
            . $this->escapeAttr($href) . '">Download Audit CSV</a></div>';
    }

    private function renderDownload(string $csv): void
    {
        $filename = 'wp-shop-editorial-en-pack-v2-'
            . (string) ($this->call)('current_time', 'Y-m-d-His') . '.csv';
        $href = 'data:text/csv;charset=utf-8;base64,' . base64_encode($csv);
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><h2>EN Translation Pack v2 — READY</h2>';
        echo '<p>Заполняй только EN Short HTML / EN Long HTML / EN Meta. RU и fingerprints не менять.</p>';
        echo '<a class="button button-primary" download="' . $this->escapeAttr($filename) . '" href="'
            . $this->escapeAttr($href) . '">Download EN Pack v2 CSV</a></div>';
    }

    private function renderImport(string $search, int $page, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><h2>Import translated EN Pack v2</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        $this->nonceField();
        $this->hiddenState($search, $page, $perPage);
        echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="import_en_pack">';
        echo '<input type="file" name="editorial_en_pack" accept=".csv,text/csv" required> ';
        echo '<button class="button button-primary">Validate + Import EN Pack v2</button></form></div>';
    }

    private function renderManualEditor(int $manualId, string $search, int $page, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px"><h2>4. Manual RU+EN Editorial</h2>';
        echo '<p>Для исключений из генератора: RU Short/Long/Meta + EN Short/Long/Meta сохраняются сначала только как staging draft. Товар меняется исключительно отдельной кнопкой Apply Manual после проверки структуры.</p>';
        echo '<form method="get" style="margin-bottom:14px">';
        echo '<input type="hidden" name="page" value="' . $this->escapeAttr($this->slug()) . '">';
        echo '<input type="hidden" name="editorial_search" value="' . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="' . $page . '">';
        echo '<input type="hidden" name="editorial_per_page" value="' . $perPage . '">';
        echo '<label><strong>Product ID</strong> <input type="number" min="1" name="manual_id" value="'
            . ($manualId > 0 ? $manualId : '') . '" required></label> ';
        echo '<button class="button">Load Manual Editor</button></form>';

        if ($manualId <= 0) {
            echo '</div>';
            return;
        }

        try {
            $editor = $this->migration->manualEditor($manualId);
        } catch (Throwable $exception) {
            echo '<div class="notice notice-error inline"><p>'
                . $this->escape($exception->getMessage()) . '</p></div></div>';
            return;
        }

        echo '<p><strong>#' . $manualId . ' ' . $this->escape((string) $editor['title']) . '</strong>';
        echo ' &nbsp; Type: <strong>' . $this->escape((string) $editor['productType']) . '</strong>';
        echo ' &nbsp; Developer: <strong>' . $this->escape((string) $editor['developer']) . '</strong>';
        echo ' &nbsp; Manual status: <strong>' . $this->escape((string) $editor['status']) . '</strong></p>';

        if ((string) $editor['issue'] !== '') {
            echo '<div class="notice notice-warning inline"><p><strong>REVIEW:</strong> '
                . $this->escape((string) $editor['issue']) . '</p></div>';
        } elseif ($editor['status'] === 'READY') {
            echo '<div class="notice notice-success inline"><p><strong>MANUAL PREVIEW READY.</strong> Source unchanged, all six fields are present, RU/EN structural map is valid. Product content has not been changed by Preview.</p></div>';
        }

        echo '<form method="post">';
        $this->nonceField();
        $this->hiddenState($search, $page, $perPage);
        echo '<input type="hidden" name="manual_id" value="' . $manualId . '">';
        echo '<input type="hidden" name="manual_product_id" value="' . $manualId . '">';
        echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="save_manual_draft">';
        echo '<table class="widefat striped"><thead><tr><th>Field</th><th>Current</th><th>Generated v28</th><th>Manual Draft</th></tr></thead><tbody>';

        $fields = [
            'ruShort' => ['RU Short HTML', 'manual_ru_short'],
            'ruLong' => ['RU Long HTML', 'manual_ru_long'],
            'ruMeta' => ['RU Meta', 'manual_ru_meta'],
            'enShort' => ['EN Short HTML', 'manual_en_short'],
            'enLong' => ['EN Long HTML', 'manual_en_long'],
            'enMeta' => ['EN Meta', 'manual_en_meta'],
        ];
        foreach ($fields as $key => [$label, $name]) {
            $height = str_contains($key, 'Long') ? 210 : 100;
            echo '<tr><th>' . $this->escape($label) . '</th>';
            echo '<td><textarea readonly style="width:100%;height:' . $height . 'px">'
                . $this->escape((string) $editor['current'][$key]) . '</textarea></td>';
            echo '<td><textarea readonly style="width:100%;height:' . $height . 'px">'
                . $this->escape((string) $editor['generated'][$key]) . '</textarea></td>';
            echo '<td><textarea name="' . $this->escapeAttr($name) . '" style="width:100%;height:'
                . $height . 'px" required>' . $this->escape((string) $editor['draft'][$key])
                . '</textarea></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><button class="button button-primary">Save + Preview Manual</button> ';
        echo '<span>Эта кнопка сохраняет только staging draft и проверяет RU/EN structure. WooCommerce content и TranslatePress не изменяются.</span></p>';
        echo '</form>';

        if (! empty($editor['hasDraft'])) {
            echo '<p><strong>Apply Manual использует последний сохранённый draft.</strong> Если текст выше был отредактирован после Preview — сначала снова нажми Save + Preview Manual.</p>';
            if ($editor['status'] === 'READY') {
                echo '<form method="post" style="display:inline-block;margin-right:8px">';
                $this->nonceField();
                $this->hiddenState($search, $page, $perPage);
                echo '<input type="hidden" name="manual_id" value="' . $manualId . '">';
                echo '<input type="hidden" name="manual_product_id" value="' . $manualId . '">';
                echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="apply_manual">';
                echo '<button class="button button-primary">Apply Manual</button></form>';
            }
            echo '<form method="post" style="display:inline-block">';
            $this->nonceField();
            $this->hiddenState($search, $page, $perPage);
            echo '<input type="hidden" name="manual_id" value="' . $manualId . '">';
            echo '<input type="hidden" name="manual_product_id" value="' . $manualId . '">';
            echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="discard_manual">';
            echo '<button class="button">Discard Manual Draft</button></form>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $editor
     * @return list<string>
     */
    private function manualPreviewLogs(array $editor): array
    {
        $logs = [
            'MANUAL EDITORIAL PREVIEW = ' . (string) $editor['status'],
            'PRODUCT ID = ' . (int) $editor['productId'],
            'TECHNICAL TYPE = ' . (string) $editor['productType'],
            'SOURCE UNCHANGED = ' . (! empty($editor['sourceCurrent']) ? 'YES' : 'NO'),
            'PRODUCT CONTENT WRITES = NO',
        ];
        if ((string) $editor['issue'] !== '') {
            $logs[] = 'REVIEW = ' . (string) $editor['issue'];
            $logs[] = 'NEXT = FIX MANUAL DRAFT';
        } else {
            $logs[] = 'RU / EN STRUCTURE = READY';
            $logs[] = 'NEXT = APPLY MANUAL';
        }
        return $logs;
    }

    /** @return array<string,string> */
    private function manualPostedContent(): array
    {
        return [
            'ruShort' => $this->postedManual('manual_ru_short'),
            'ruLong' => $this->postedManual('manual_ru_long'),
            'ruMeta' => $this->postedManual('manual_ru_meta'),
            'enShort' => $this->postedManual('manual_en_short'),
            'enLong' => $this->postedManual('manual_en_long'),
            'enMeta' => $this->postedManual('manual_en_meta'),
        ];
    }

    private function postedManual(string $key): string
    {
        if (! isset($_POST[$key]) || ! is_scalar($_POST[$key])) {
            return '';
        }
        $value = (string) $_POST[$key];
        $unslashed = ($this->call)('wp_unslash', $value);
        return is_scalar($unslashed) ? (string) $unslashed : $value;
    }

    /** @param array<string, mixed> $preview */
    private function renderPreview(array $preview, string $search, int $page, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px"><h2>Preview — #'
            . (int) $preview['productId'] . ' ' . $this->escape((string) $preview['title']) . '</h2>';
        echo '<p><strong>Overall:</strong> ' . $this->escape((string) $preview['status'])
            . ' &nbsp; <strong>Type:</strong> ' . $this->escape((string) $preview['productType'])
            . ' &nbsp; <strong>Developer:</strong> ' . $this->escape((string) $preview['developer']) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>Field</th><th>Current</th><th>Generated v28</th></tr></thead><tbody>';
        foreach (['ruShort' => 'RU Short', 'ruLong' => 'RU Long', 'ruMeta' => 'RU Meta',
            'enShort' => 'EN Short', 'enLong' => 'EN Long', 'enMeta' => 'EN Meta'] as $key => $label) {
            $height = str_contains($key, 'Long') ? 180 : 90;
            echo '<tr><th>' . $label . '</th><td><textarea readonly style="width:100%;height:' . $height . 'px">'
                . $this->escape((string) $preview['current'][$key]) . '</textarea></td><td>';
            echo '<textarea readonly style="width:100%;height:' . $height . 'px">'
                . $this->escape((string) $preview['generated'][$key]) . '</textarea></td></tr>';
        }
        echo '</tbody></table><form method="post">';
        $this->nonceField();
        $this->hiddenState($search, $page, $perPage);
        echo '<input type="hidden" name="preview_id" value="' . (int) $preview['productId'] . '">';
        echo '<button class="button button-primary" name="editorial_apply_id" value="'
            . (int) $preview['productId'] . '">Apply this product</button>';
        if (! empty($preview['backupAvailable'])) {
            echo ' <button class="button" name="editorial_restore_id" value="'
                . (int) $preview['productId'] . '">Restore backup</button>';
        }
        echo '</form></div>';
    }

    /** @param list<int> $selected */
    private function assertBatchReady(array $selected): void
    {
        $builder = new TranslationMapBuilder();

        foreach ($selected as $id) {
            $preview = $this->previewForPack($id);
            if ($preview['productType'] === 'unknown') {
                throw new RuntimeException('Batch preflight failed: product #' . $id . ' has unknown type.');
            }
            if ($preview['status'] === 'STOP') {
                throw new RuntimeException('Batch preflight failed: product #' . $id . ' is STOP / EN REVIEW.');
            }

            $target = $preview['generated'];
            try {
                $builder->build(
                    $target['ruShort'],
                    $target['ruLong'],
                    $target['ruMeta'],
                    $target['enShort'],
                    $target['enLong'],
                    $target['enMeta']
                );
            } catch (InvalidArgumentException $exception) {
                throw new RuntimeException(
                    'Batch preflight failed for product #' . $id . ': ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }
    }

    /** @param list<int> $selected */
    private function preparePack(array $selected): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to create EN pack stream.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $this->headers(), ';', '"', '');
        foreach ($selected as $id) {
            $preview = $this->previewForPack($id);
            if (! $this->packEligible($preview)) {
                fclose($stream);
                throw new RuntimeException(
                    'Product #' . $id . ' is not eligible for EN pack v2. Expected STOP / EN REVIEW or MIGRATE.'
                );
            }
            $current = $preview['current'];
            $target = $preview['generated'];
            fputcsv($stream, [
                self::PACK_VERSION,
                (string) $id,
                $preview['title'],
                $preview['productType'],
                $preview['developer'],
                $this->fingerprint($current),
                $this->fingerprint($target),
                $target['ruShort'],
                $target['ruLong'],
                $target['ruMeta'],
                '',
                '',
                '',
            ], ';', '"', '');
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
    private function importPack(): array
    {
        $upload = $_FILES['editorial_en_pack'] ?? null;
        if (! is_array($upload)) {
            throw new RuntimeException('EN pack CSV was not uploaded.');
        }
        $error = is_scalar($upload['error'] ?? null) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;
        $name = is_scalar($upload['name'] ?? null) ? (string) $upload['name'] : '';
        $tmp = is_scalar($upload['tmp_name'] ?? null) ? (string) $upload['tmp_name'] : '';
        $size = is_scalar($upload['size'] ?? null) ? (int) $upload['size'] : 0;
        if ($error !== UPLOAD_ERR_OK || $tmp === '') {
            throw new RuntimeException('EN pack upload failed with code ' . $error . '.');
        }
        if ($size <= 0 || $size > 8 * 1024 * 1024 || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('EN pack must be a .csv file between 1 byte and 8 MB.');
        }
        $stream = fopen($tmp, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open uploaded EN pack.');
        }
        try {
            $headers = fgetcsv($stream, 0, ';', '"', '');
            if (! is_array($headers)) {
                throw new RuntimeException('EN pack header is missing.');
            }
            $headers = array_map(static fn(mixed $v): string => is_scalar($v) ? trim((string) $v) : '', $headers);
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            if ($headers !== $this->headers()) {
                throw new RuntimeException('EN pack header does not match v2 format.');
            }
            $rows = [];
            while (($csvRow = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                if ($this->rowEmpty($csvRow)) {
                    continue;
                }
                if (count($csvRow) !== count($headers)) {
                    throw new RuntimeException('EN pack contains a malformed CSV row.');
                }
                $rows[] = array_combine($headers, $csvRow);
                if (count($rows) > 25) {
                    throw new RuntimeException('EN pack import is limited to 25 products.');
                }
            }
        } finally {
            fclose($stream);
        }
        if ($rows === []) {
            throw new RuntimeException('EN pack contains no product rows.');
        }

        $builder = new TranslationMapBuilder();
        $prepared = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) trim((string) ($row['Product ID'] ?? '0'));
            if ($id <= 0 || isset($seen[$id])) {
                throw new RuntimeException('EN pack contains an invalid or duplicate Product ID.');
            }
            $seen[$id] = true;
            if (trim((string) ($row['Pack Version'] ?? '')) !== self::PACK_VERSION) {
                throw new RuntimeException('Unsupported EN pack version for product #' . $id . '.');
            }
            $preview = $this->previewForPack($id);
            if ((string) ($row['Type'] ?? '') !== $preview['productType']) {
                throw new RuntimeException('Product #' . $id . ' type changed after export.');
            }
            $current = $preview['current'];
            $target = $preview['generated'];
            if (! hash_equals($this->fingerprint($current), trim((string) ($row['Source RU Fingerprint'] ?? '')))) {
                throw new RuntimeException('Product #' . $id . ' source RU changed after export. Re-export v2.');
            }
            if (! hash_equals($this->fingerprint($target), trim((string) ($row['Target RU Fingerprint'] ?? '')))) {
                throw new RuntimeException('Product #' . $id . ' Generated v28 RU changed after export. Re-export v2.');
            }
            foreach (['Target RU Short HTML' => 'ruShort', 'Target RU Long HTML' => 'ruLong', 'Target RU Meta' => 'ruMeta'] as $column => $field) {
                if ((string) ($row[$column] ?? '') !== $target[$field]) {
                    throw new RuntimeException('Product #' . $id . ' target RU columns were modified.');
                }
            }
            $enShort = trim((string) ($row['EN Short HTML'] ?? ''));
            $enLong = trim((string) ($row['EN Long HTML'] ?? ''));
            $enMeta = trim((string) ($row['EN Meta'] ?? ''));
            try {
                $builder->build($target['ruShort'], $target['ruLong'], $target['ruMeta'], $enShort, $enLong, $enMeta);
            } catch (InvalidArgumentException $exception) {
                throw new RuntimeException(
                    'Product #' . $id . ' target translation validation failed: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
            $prepared[] = [
                'id' => $id,
                'short' => $enShort,
                'long' => $enLong,
                'meta' => $enMeta,
                'targetFingerprint' => trim((string) ($row['Target RU Fingerprint'] ?? '')),
            ];
        }

        $logs = [
            'EDITORIAL EN PACK V2 IMPORT = VALIDATED',
            'PRODUCTS = ' . count($prepared),
            'SOURCE RU = PRESERVED',
            'TARGET GENERATED V28 RU = VERIFIED',
        ];
        foreach ($prepared as $item) {
            $id = $item['id'];
            $short = (string) ($this->call)('wp_kses_post', $item['short']);
            $long = (string) ($this->call)('wp_kses_post', $item['long']);
            $meta = (string) ($this->call)('sanitize_textarea_field', $item['meta']);
            ($this->call)('update_post_meta', $id, '_wp_shop_en_short_description', $short);
            ($this->call)('update_post_meta', $id, '_wp_shop_en_long_description', $long);
            ($this->call)('update_post_meta', $id, '_wp_shop_en_meta_description', $meta);
            ($this->call)('update_post_meta', $id, self::EN_TARGET_RU_FINGERPRINT_META, $item['targetFingerprint']);
            ($this->call)(
                'update_post_meta',
                $id,
                self::EN_CONTENT_FINGERPRINT_META,
                $this->englishFingerprint($short, $long, $meta)
            );
            $post = $this->previewForPack($id);
            $logs[] = 'PRODUCT ' . $id . ' = TARGET EN PREPARED / OVERALL ' . $post['status'];
        }
        $logs[] = 'TRANSLATEPRESS SYNC = NOT RUN';
        $logs[] = 'NEXT = REVIEW PREVIEW, THEN APPLY SELECTED';
        return $logs;
    }

    /** @return list<string> */
    private function headers(): array
    {
        return [
            'Pack Version', 'Product ID', 'Product', 'Type', 'Developer',
            'Source RU Fingerprint', 'Target RU Fingerprint', 'Target RU Short HTML',
            'Target RU Long HTML', 'Target RU Meta', 'EN Short HTML', 'EN Long HTML', 'EN Meta',
        ];
    }

    /** @param array<string, string> $content */
    private function fingerprint(array $content): string
    {
        return hash('sha256', $content['ruShort'] . "\0" . $content['ruLong'] . "\0" . $content['ruMeta']);
    }

    private function englishFingerprint(string $short, string $long, string $meta): string
    {
        return hash('sha256', $short . "\0" . $long . "\0" . $meta);
    }

    /** @param array<string, mixed> $preview */
    private function packEligible(array $preview): bool
    {
        return $preview['productType'] !== 'unknown'
            && (($preview['status'] === 'STOP' && $preview['enStatus'] === 'REVIEW')
                || $preview['status'] === 'MIGRATE');
    }

    /** @return array<string, mixed> */
    private function previewForPack(int $id): array
    {
        $had = array_key_exists('editorial_selected', $_POST);
        $old = $_POST['editorial_selected'] ?? null;
        $_POST['editorial_selected'] = [(string) $id];
        try {
            return $this->migration->preview($id);
        } finally {
            if ($had) {
                $_POST['editorial_selected'] = $old;
            } else {
                unset($_POST['editorial_selected']);
            }
        }
    }

    /** @param array<int, mixed> $row */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param list<int> $ids */
    private function limitSelected(array $ids, string $label): void
    {
        if ($ids === []) {
            throw new RuntimeException('No products selected.');
        }
        if (count($ids) > 25) {
            throw new RuntimeException($label . ' is limited to 25 products per run.');
        }
    }

    private function renderPagination(string $search, int $page, int $perPage, bool $hasNext): void
    {
        echo '<p>';
        if ($page > 1) {
            echo '<a class="button" href="' . $this->escapeUrl($this->pageUrl([
                'editorial_search' => $search, 'editorial_page' => (string) ($page - 1),
                'editorial_per_page' => (string) $perPage,
            ])) . '">← Previous</a> ';
        }
        echo '<strong>Page ' . $page . '</strong> ';
        if ($hasNext) {
            echo '<a class="button" href="' . $this->escapeUrl($this->pageUrl([
                'editorial_search' => $search, 'editorial_page' => (string) ($page + 1),
                'editorial_per_page' => (string) $perPage,
            ])) . '">Next →</a>';
        }
        echo '</p>';
    }

    /** @return list<int> */
    private function productIds(string $search, int $page, int $limit): array
    {
        $args = [
            'post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => $limit, 'offset' => ($page - 1) * ($limit - 1),
            'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
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
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
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
            if (is_scalar($value) && ($id = (int) $value) > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function hiddenState(string $search, int $page, int $perPage): void
    {
        echo '<input type="hidden" name="editorial_search" value="' . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="' . $page . '">';
        echo '<input type="hidden" name="editorial_per_page" value="' . $perPage . '">';
    }

    private function perPage(int $value): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : 25;
    }

    private function badge(string $status): string
    {
        $style = $status === 'CURRENT' ? 'color:#008a20;font-weight:700;'
            : ($status === 'MISSING' ? 'color:#b32d2e;font-weight:700;' : 'color:#996800;font-weight:700;');
        return '<span style="' . $style . '">' . $this->escape($status) . '</span>';
    }

    /** @param list<string> $logs */
    private function renderLogs(array $logs, ?bool $success): void
    {
        if ($logs !== []) {
            $class = $success === true ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . '"><pre>' . $this->escape(implode("\n", $logs)) . '</pre></div>';
        }
    }

    /** @param array<string, string> $args */
    private function pageUrl(array $args): string
    {
        return (string) ($this->call)('admin_url', 'admin.php?' . http_build_query(array_merge([
            'page' => $this->slug(),
        ], $args)));
    }

    private function nonceField(): void
    {
        ($this->call)('wp_nonce_field', 'wp_shop_pm_editorial_migration', '_wpnonce', true, true);
    }

    private function checkNonce(): void
    {
        ($this->call)('check_admin_referer', 'wp_shop_pm_editorial_migration');
    }

    private function posted(string $key): string
    {
        if (! isset($_POST[$key]) || ! is_scalar($_POST[$key])) {
            return '';
        }
        return trim((string) ($this->call)('sanitize_text_field', (string) $_POST[$key]));
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
        return trim((string) ($this->call)('sanitize_text_field', (string) $value));
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
