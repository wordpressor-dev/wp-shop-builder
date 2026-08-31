from pathlib import Path

p = Path('apps/Plugin/Admin/ProductEditorialMigrationPage.php')
s = p.read_text()

old = "        $csv = '';\n"
new = "        $csv = '';\n        $auditCsv = '';\n"
if old not in s:
    raise SystemExit('csv state anchor missing')
s = s.replace(old, new, 1)

old = """            } elseif ($action === 'import_en_pack') {
                $this->checkNonce();
                $logs = $this->importPack();
                $success = true;
            }
"""
new = """            } elseif ($action === 'prepare_audit_page') {
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
"""
if old not in s:
    raise SystemExit('action anchor missing')
s = s.replace(old, new, 1)

old = """        if ($csv !== '') {
            $this->renderDownload($csv);
        }
        $this->renderImport($search, $page, $perPage);
"""
new = """        if ($csv !== '') {
            $this->renderDownload($csv);
        }
        if ($auditCsv !== '') {
            $this->renderAuditDownload($auditCsv, $page);
        }
        $this->renderImport($search, $page, $perPage);
"""
if old not in s:
    raise SystemExit('render download anchor missing')
s = s.replace(old, new, 1)

old = """        echo '<button class=\"button\" name=\"wp_shop_pm_editorial_action\" value=\"prepare_en_pack\">Prepare EN pack v2 (max 25)</button> ';
        echo '<button class=\"button button-primary\" name=\"wp_shop_pm_editorial_action\" value=\"apply_selected\">Apply selected (max 25)</button> ';
        echo '<span>EN pack v2: STOP / EN REVIEW или MIGRATE. Apply selected выполняется только если вся выбранная пачка прошла preflight.</span></p>';
"""
new = """        echo '<button class=\"button\" name=\"wp_shop_pm_editorial_action\" value=\"prepare_audit_page\">Audit CSV — current page</button> ';
        echo '<button class=\"button\" name=\"wp_shop_pm_editorial_action\" value=\"prepare_en_pack\">Prepare EN pack v2 (max 25)</button> ';
        echo '<button class=\"button button-primary\" name=\"wp_shop_pm_editorial_action\" value=\"apply_selected\">Apply selected (max 25)</button> ';
        echo '<span>Audit CSV ничего не записывает и выгружает Current + Generated v28 для текущей страницы. EN pack v2: STOP / EN REVIEW или MIGRATE. Apply selected выполняется только если вся выбранная пачка прошла preflight.</span></p>';
"""
if old not in s:
    raise SystemExit('table button anchor missing')
s = s.replace(old, new, 1)

marker = "    private function renderDownload(string $csv): void\n"
if marker not in s:
    raise SystemExit('renderDownload marker missing')

methods = r'''    /** @param list<int> $ids */
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

'''
s = s.replace(marker, methods + marker, 1)
p.write_text(s)
