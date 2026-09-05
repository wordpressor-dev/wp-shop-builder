<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditRow;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class VendorProductNamingAuditPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_vendor_naming_audit_report_v1';
    private const STATE_META_KEY = 'wp_shop_pm_vendor_naming_audit_state_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorProductNamingAuditService $audit,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-vendor-naming-audit';
    }

    public function title(): string
    {
        return 'Vendor Naming Audit';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_vendor_naming_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;

        if ($action === 'audit_start') {
            $this->checkNonce();
            $limit = $this->limit(
                (int) $this->posted('audit_limit', '10')
            );
            $this->resetReport();
            $state = $this->newState(
                $limit,
                $this->audit->candidateCount()
            );
            $this->saveState($state);
            [$state, $message, $error] = $this->processNextBatch($state);
            $autoContinue = $error === '' && $state['status'] === 'RUNNING';
        } elseif (
            $action === 'audit_next'
            || $action === 'audit_resume'
        ) {
            $this->checkNonce();

            if ($state['status'] !== 'RUNNING') {
                $error = 'No running Vendor Product Naming Audit was found. Start a new audit.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->summary($report);
        $attention = $this->attentionRows($report['rows']);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Vendor Product Naming Audit</h1>';
        echo '<p><strong>DRY RUN ONLY.</strong> Audits published Vendor products and compares the current WooCommerce title with Plugin Name / Theme Name read from the current local Vendor ZIP. Product posts, slugs, SEO fields, content, images and metadata are never written.</p>';
        echo '<p><strong>Marketplace protection:</strong> ThemeForest, CodeCanyon and Envato sales pages are unconditionally excluded, even if legacy source metadata is missing or incorrect.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>VENDOR NAMING AUDIT ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderProgress($state, $summary);
        $this->renderControls($state);

        if ($state['status'] === 'READY') {
            $this->renderExport();
        }

        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">RENAME / REVIEW</h2>';

        if ($attention === []) {
            echo '<p><strong>No naming changes or manual reviews are present in the saved report.</strong></p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach (
                [
                    'ID',
                    'Current title',
                    'ZIP header',
                    'Recommended title',
                    'Type',
                    'Action',
                    'Confidence',
                    'Evidence',
                    'Reason',
                    'Product',
                ]
                as $heading
            ) {
                echo '<th>' . $this->escape($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($attention as $row) {
                $productId = (int) ($row['productId'] ?? 0);
                echo '<tr>';
                echo '<td>' . $this->escape((string) $productId) . '</td>';
                echo '<td>' . $this->escape((string) ($row['currentTitle'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['headerName'] ?? '')) . '</td>';
                echo '<td><strong>' . $this->escape((string) ($row['recommendedTitle'] ?? '')) . '</strong></td>';
                echo '<td>' . $this->escape((string) ($row['productType'] ?? '')) . '</td>';
                echo '<td><strong>' . $this->escape((string) ($row['action'] ?? '')) . '</strong></td>';
                echo '<td>' . $this->escape((string) ($row['confidence'] ?? '')) . '</td>';
                echo '<td><code>' . $this->escape((string) ($row['evidence'] ?? '')) . '</code></td>';
                echo '<td>' . $this->escape((string) ($row['reason'] ?? '')) . '</td>';
                echo '<td>';
                $editUrl = (string) ($this->call)(
                    'get_edit_post_link',
                    $productId,
                    'raw'
                );
                if ($editUrl !== '') {
                    echo '<a class="button button-secondary" href="'
                        . $this->escapeUrl($editUrl)
                        . '">Edit product</a>';
                } else {
                    echo '—';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';

        if ($autoContinue) {
            $this->renderAutoContinue();
        }

        echo '</div>';
    }

    public function exportCsv(): void
    {
        if (! (bool) ($this->call)('current_user_can', $this->capability())) {
            ($this->call)('wp_die', 'You are not allowed to export this report.');

            return;
        }

        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_export_vendor_naming_audit',
            '_wpnonce'
        );
        $report = $this->loadReport();
        $rows = $this->allRows($report['rows']);
        $filename = 'wp-shop-vendor-product-naming-audit-'
            . (string) ($this->call)('current_time', 'Y-m-d-His')
            . '.csv';

        ($this->call)('nocache_headers');
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="'
            . $filename
            . '"'
        );
        header('X-Content-Type-Options: nosniff');

        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            ($this->call)('wp_die', 'Unable to open CSV output stream.');

            return;
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            [
                'Product ID',
                'Current Title',
                'Current Base Title',
                'ZIP Header Name',
                'Recommended Title',
                'Product Type',
                'Action',
                'Confidence',
                'Evidence',
                'Reason',
            ],
            ';',
            '"',
            ''
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                [
                    (string) ($row['productId'] ?? ''),
                    (string) ($row['currentTitle'] ?? ''),
                    (string) ($row['currentBaseTitle'] ?? ''),
                    (string) ($row['headerName'] ?? ''),
                    (string) ($row['recommendedTitle'] ?? ''),
                    (string) ($row['productType'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['confidence'] ?? ''),
                    (string) ($row['evidence'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                ],
                ';',
                '"',
                ''
            );
        }

        fclose($stream);
        exit;
    }

    /**
     * @param array<string, int|string> $state
     * @return array{array<string, int|string>, string, string}
     */
    private function processNextBatch(array $state): array
    {
        $offset = (int) $state['next_offset'];
        $limit = $this->limit((int) $state['limit']);

        try {
            $rows = $this->audit->scan($offset, $limit);
            $this->saveReportRows($rows);
        } catch (Throwable $exception) {
            $state['status'] = 'FAILED';
            $state['error'] = $exception->getMessage();
            $state['updated_at'] = $this->currentTime();
            $this->saveState($state);

            return [$state, '', $exception->getMessage()];
        }

        $report = $this->loadReport();
        $state['processed'] = count($report['seen']);
        $state['next_offset'] = $offset + $limit;
        $state['updated_at'] = $this->currentTime();
        $state['error'] = '';

        $finished = count($rows) < $limit
            || $state['processed'] >= (int) $state['total'];

        if ($finished) {
            $state['status'] = 'READY';
            $state['processed'] = min(
                (int) $state['processed'],
                (int) $state['total']
            );
            $message = 'VENDOR PRODUCT NAMING AUDIT = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'VENDOR PRODUCT NAMING AUDIT BATCH = SAVED';
        }

        $this->saveState($state);

        return [$state, $message, ''];
    }

    /**
     * @param list<VendorProductNamingAuditRow> $rows
     */
    private function saveReportRows(array $rows): void
    {
        $report = $this->loadReport();

        if ($report['started_at'] === '') {
            $report['started_at'] = $this->currentTime();
        }

        foreach ($rows as $row) {
            $id = $row->productId;
            $report['seen'][$id] = $row->action;
            $report['rows'][$id] = [
                'productId' => $row->productId,
                'currentTitle' => $row->currentTitle,
                'currentBaseTitle' => $row->currentBaseTitle,
                'headerName' => $row->headerName,
                'recommendedTitle' => $row->recommendedTitle,
                'productType' => $row->productType,
                'action' => $row->action,
                'confidence' => $row->confidence,
                'evidence' => $row->evidence,
                'reason' => $row->reason,
            ];
        }

        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
    }

    /**
     * @param array<string, mixed> $report
     * @return array{scanned:int,keep:int,rename:int,review:int}
     */
    private function summary(array $report): array
    {
        $summary = [
            'scanned' => count($report['seen']),
            'keep' => 0,
            'rename' => 0,
            'review' => 0,
        ];

        foreach ($report['seen'] as $action) {
            if ($action === 'KEEP') {
                ++$summary['keep'];
            } elseif ($action === 'RENAME') {
                ++$summary['rename'];
            } elseif ($action === 'REVIEW') {
                ++$summary['review'];
            }
        }

        return $summary;
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function allRows(array $stored): array
    {
        $rows = [];

        foreach ($stored as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int =>
                (int) ($left['productId'] ?? 0)
                <=> (int) ($right['productId'] ?? 0)
        );

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function attentionRows(array $stored): array
    {
        return array_values(array_filter(
            $this->allRows($stored),
            static fn (array $row): bool =>
                (string) ($row['action'] ?? '') !== 'KEEP'
        ));
    }

    /**
     * @param array<string, int|string> $state
     * @param array{scanned:int,keep:int,rename:int,review:int} $summary
     */
    private function renderProgress(array $state, array $summary): void
    {
        $total = (int) $state['total'];
        $processed = (int) $state['processed'];
        $percent = $total > 0
            ? min(100, (int) floor(($processed / $total) * 100))
            : ($state['status'] === 'READY' ? 100 : 0);

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>VENDOR NAMING AUDIT = '
            . $this->escape((string) $state['status'])
            . '</strong> &nbsp; PROCESSED = '
            . $this->escape((string) $processed)
            . ' &nbsp; VENDOR TOTAL = '
            . $this->escape((string) $total)
            . ' &nbsp; PROGRESS = '
            . $this->escape((string) $percent)
            . '%</p>';
        echo '<p><strong>PRODUCT WRITES = 0</strong> &nbsp; '
            . 'SCANNED = ' . $this->escape((string) $summary['scanned'])
            . ' &nbsp; KEEP = ' . $this->escape((string) $summary['keep'])
            . ' &nbsp; RENAME = ' . $this->escape((string) $summary['rename'])
            . ' &nbsp; REVIEW = ' . $this->escape((string) $summary['review'])
            . '</p>';

        if ((string) $state['updated_at'] !== '') {
            echo '<p>LAST SAVED = '
                . $this->escape((string) $state['updated_at'])
                . '</p>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, int|string> $state
     */
    private function renderControls(array $state): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Published Vendor Product Naming Audit</h2>';
        echo '<p>Start rebuilds the report from the beginning. The audit only stores this administrator\'s progress/report in user meta. Recommended batch size is 10 because current Vendor ZIP headers are inspected.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_naming_action" value="audit_start">';
        echo '<p><label><strong>Batch size</strong><br><input type="number" min="1" max="25" name="audit_limit" value="'
            . $this->escapeAttr((string) $state['limit'])
            . '" style="width:180px;"></label></p>';
        echo '<button type="submit" class="button button-primary">Запустить Vendor Naming Audit — без записи</button>';
        echo '</form>';

        if ($state['status'] === 'RUNNING') {
            echo '<form method="post" style="margin-top:12px;">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_vendor_naming_action" value="audit_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Vendor Naming Audit</button>';
            echo '</form>';
        }

        if ($state['status'] === 'FAILED' && $state['error'] !== '') {
            echo '<p><strong>LAST ERROR:</strong> '
                . $this->escape((string) $state['error'])
                . '</p>';
        }

        echo '</div>';
    }

    private function renderExport(): void
    {
        $action = (string) ($this->call)(
            'admin_url',
            'admin-post.php'
        );

        echo '<form method="post" action="'
            . $this->escapeUrl($action)
            . '" style="margin:12px 0;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_export_vendor_naming_audit',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="wp_shop_pm_export_vendor_naming_audit">';
        echo '<button type="submit" class="button button-secondary">Export full Vendor Naming CSV</button>';
        echo '</form>';
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-vendor-naming-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_naming_action" value="audit_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-vendor-naming-next");if(f){f.submit();}},900);';
        echo '</script>';
        echo '<p><em>Следующий пакет Vendor Naming Audit запустится автоматически…</em></p>';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(): array
    {
        return [
            'seen' => [],
            'rows' => [],
            'started_at' => '',
            'updated_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(): array
    {
        $report = $this->emptyReport();
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $report;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::REPORT_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $report;
        }

        foreach (['seen', 'rows'] as $key) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $report[$key] = $stored[$key];
            }
        }

        foreach (['started_at', 'updated_at'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $report[$key] = $stored[$key];
            }
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function saveReport(array $report): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::REPORT_META_KEY,
                $report
            );
        }
    }

    private function resetReport(): void
    {
        $this->saveReport($this->emptyReport());
    }

    /**
     * @return array<string, int|string>
     */
    private function newState(int $limit, int $total): array
    {
        $now = $this->currentTime();

        return [
            'status' => $total === 0 ? 'READY' : 'RUNNING',
            'limit' => $limit,
            'total' => max(0, $total),
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => $now,
            'updated_at' => $now,
            'error' => '',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function loadState(): array
    {
        $state = [
            'status' => 'IDLE',
            'limit' => 10,
            'total' => 0,
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => '',
            'updated_at' => '',
            'error' => '',
        ];
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $state;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::STATE_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $state;
        }

        foreach (['status', 'started_at', 'updated_at', 'error'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $state[$key] = $stored[$key];
            }
        }

        foreach (['limit', 'total', 'processed', 'next_offset'] as $key) {
            if (isset($stored[$key])) {
                $state[$key] = max(0, (int) $stored[$key]);
            }
        }

        $state['limit'] = $this->limit((int) $state['limit']);

        return $state;
    }

    /**
     * @param array<string, int|string> $state
     */
    private function saveState(array $state): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::STATE_META_KEY,
                $state
            );
        }
    }

    private function limit(int $limit): int
    {
        return max(1, min(25, $limit));
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_vendor_naming_audit',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_vendor_naming_audit',
            '_wpnonce',
            true,
            true
        );
    }

    private function currentUserId(): int
    {
        return (int) ($this->call)('get_current_user_id');
    }

    private function currentTime(): string
    {
        return (string) ($this->call)('current_time', 'mysql');
    }

    private function posted(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        if (! is_string($value)) {
            return $default;
        }

        return (string) ($this->call)('wp_unslash', $value);
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
