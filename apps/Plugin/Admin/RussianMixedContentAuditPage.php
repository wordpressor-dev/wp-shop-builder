<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Translation\RussianMixedContentAuditRow;
use WPShop\App\Plugin\ProductManager\Translation\RussianMixedContentAuditService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class RussianMixedContentAuditPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_ru_mixed_audit_report_v1';
    private const STATE_META_KEY = 'wp_shop_pm_ru_mixed_audit_state_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly RussianMixedContentAuditService $audit,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-ru-mixed-audit';
    }

    public function title(): string
    {
        return 'Mixed RU Audit';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_ru_mixed_audit_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;

        if ($action === 'audit_start') {
            $this->checkNonce();
            $limit = $this->limit(
                (int) $this->posted('audit_limit', '25')
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
                $error = 'No running Mixed RU Audit was found. Start a new audit.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->summary($report);
        $findingRows = $this->findingRows($report['products']);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Mixed RU Audit</h1>';
        echo '<p>Read-only audit of authoritative Russian Short Description, Long Description and SureRank Meta. HTML attributes, URLs, shortcodes and code/pre blocks are excluded before language analysis. BRAND_NAME and TERM_ONLY are informational findings; only MIXED_PROSE marks a product as REVIEW. Product content is never written.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>RU AUDIT ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderProgress($state, $summary);
        $this->renderControls($state);

        if ($state['status'] === 'READY') {
            $this->renderExport();
        }

        echo '<div class="postbox" style="max-width:1550px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">English fragments found in RU fields</h2>';

        if ($findingRows === []) {
            echo '<p><strong>No English fragments requiring classification were found in the saved audit report.</strong></p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach (
                [
                    'ID',
                    'Product',
                    'Status',
                    'Field',
                    'Classification',
                    'English fragment',
                    'Context',
                    'Product',
                ] as $heading
            ) {
                echo '<th>' . $this->escape($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($findingRows as $row) {
                $productId = (int) $row['productId'];
                echo '<tr>';
                echo '<td>' . $this->escape((string) $productId) . '</td>';
                echo '<td>' . $this->escape((string) $row['title']) . '</td>';
                echo '<td><strong>' . $this->escape((string) $row['status']) . '</strong></td>';
                echo '<td>' . $this->escape((string) $row['field']) . '</td>';
                echo '<td><strong>' . $this->escape((string) $row['classification']) . '</strong></td>';
                echo '<td><code>' . $this->escape((string) $row['fragment']) . '</code></td>';
                echo '<td>' . $this->escape((string) $row['context']) . '</td>';
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
            'wp_shop_pm_export_ru_mixed_audit',
            '_wpnonce'
        );
        $report = $this->loadReport();
        $rows = $this->findingRows($report['products']);
        $filename = 'wp-shop-ru-mixed-audit-v29-'
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
                'Product',
                'Status',
                'Field',
                'Classification',
                'English Fragment',
                'Context',
            ],
            ';',
            '"',
            ''
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                [
                    (string) $row['productId'],
                    (string) $row['title'],
                    (string) $row['status'],
                    (string) $row['field'],
                    (string) $row['classification'],
                    (string) $row['fragment'],
                    (string) $row['context'],
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
            || $state['processed'] >= $state['total'];

        if ($finished) {
            $state['status'] = 'READY';
            $state['processed'] = min(
                $state['processed'],
                $state['total']
            );
            $message = 'MIXED RU AUDIT V29 = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'MIXED RU AUDIT V29 BATCH = SAVED';
        }

        $this->saveState($state);

        return [$state, $message, ''];
    }

    /**
     * @param list<RussianMixedContentAuditRow> $rows
     */
    private function saveReportRows(array $rows): void
    {
        $report = $this->loadReport();

        if ($report['started_at'] === '') {
            $report['started_at'] = $this->currentTime();
        }

        foreach ($rows as $row) {
            $id = $row->productId;
            $report['seen'][$id] = $row->status;

            if ($row->findings === []) {
                unset($report['products'][$id]);
                continue;
            }

            $findings = [];

            foreach ($row->findings as $finding) {
                $findings[] = [
                    'field' => $finding->field,
                    'classification' => $finding->classification,
                    'fragment' => $finding->fragment,
                    'context' => $finding->context,
                ];
            }

            $report['products'][$id] = [
                'productId' => $row->productId,
                'title' => $row->title,
                'status' => $row->status,
                'findings' => $findings,
            ];
        }

        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
    }

    /**
     * @param array<string, mixed> $report
     * @return array{
     *   scanned:int,
     *   clean:int,
     *   info:int,
     *   review:int,
     *   brand:int,
     *   term:int,
     *   prose:int
     * }
     */
    private function summary(array $report): array
    {
        $summary = [
            'scanned' => count($report['seen']),
            'clean' => 0,
            'info' => 0,
            'review' => 0,
            'brand' => 0,
            'term' => 0,
            'prose' => 0,
        ];

        foreach ($report['seen'] as $status) {
            if ($status === 'CLEAN') {
                ++$summary['clean'];
            } elseif ($status === 'INFO') {
                ++$summary['info'];
            } elseif ($status === 'REVIEW') {
                ++$summary['review'];
            }
        }

        foreach ($report['products'] as $product) {
            if (! is_array($product)) {
                continue;
            }

            foreach ((array) ($product['findings'] ?? []) as $finding) {
                if (! is_array($finding)) {
                    continue;
                }

                $classification = (string) (
                    $finding['classification'] ?? ''
                );

                if ($classification === 'BRAND_NAME') {
                    ++$summary['brand'];
                } elseif ($classification === 'TERM_ONLY') {
                    ++$summary['term'];
                } elseif ($classification === 'MIXED_PROSE') {
                    ++$summary['prose'];
                }
            }
        }

        return $summary;
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function findingRows(array $stored): array
    {
        $rows = [];

        foreach ($stored as $product) {
            if (! is_array($product)) {
                continue;
            }

            foreach ((array) ($product['findings'] ?? []) as $finding) {
                if (! is_array($finding)) {
                    continue;
                }

                $rows[] = [
                    'productId' => (int) ($product['productId'] ?? 0),
                    'title' => (string) ($product['title'] ?? ''),
                    'status' => (string) ($product['status'] ?? ''),
                    'field' => (string) ($finding['field'] ?? ''),
                    'classification' => (string) (
                        $finding['classification'] ?? ''
                    ),
                    'fragment' => (string) ($finding['fragment'] ?? ''),
                    'context' => (string) ($finding['context'] ?? ''),
                ];
            }
        }

        usort(
            $rows,
            static function (array $left, array $right): int {
                $byId = (int) $left['productId']
                    <=> (int) $right['productId'];

                if ($byId !== 0) {
                    return $byId;
                }

                return strcmp(
                    (string) $left['field']
                        . (string) $left['classification']
                        . (string) $left['fragment'],
                    (string) $right['field']
                        . (string) $right['classification']
                        . (string) $right['fragment']
                );
            }
        );

        return $rows;
    }

    /**
     * @param array<string, int|string> $state
     * @param array{
     *   scanned:int,
     *   clean:int,
     *   info:int,
     *   review:int,
     *   brand:int,
     *   term:int,
     *   prose:int
     * } $summary
     */
    private function renderProgress(array $state, array $summary): void
    {
        $total = (int) $state['total'];
        $processed = (int) $state['processed'];
        $percent = $total > 0
            ? min(100, (int) floor(($processed / $total) * 100))
            : ($state['status'] === 'READY' ? 100 : 0);

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>MIXED RU AUDIT V29 = '
            . $this->escape((string) $state['status'])
            . '</strong> &nbsp; PROCESSED = '
            . $this->escape((string) $processed)
            . ' &nbsp; TOTAL = '
            . $this->escape((string) $total)
            . ' &nbsp; PROGRESS = '
            . $this->escape((string) $percent)
            . '%</p>';
        echo '<p><strong>PRODUCTS:</strong> CLEAN = '
            . $this->escape((string) $summary['clean'])
            . ' &nbsp; INFO = '
            . $this->escape((string) $summary['info'])
            . ' &nbsp; REVIEW = '
            . $this->escape((string) $summary['review'])
            . '</p>';
        echo '<p><strong>FINDINGS:</strong> BRAND_NAME = '
            . $this->escape((string) $summary['brand'])
            . ' &nbsp; TERM_ONLY = '
            . $this->escape((string) $summary['term'])
            . ' &nbsp; MIXED_PROSE = '
            . $this->escape((string) $summary['prose'])
            . '</p>';
        echo '<p><strong>REPORT STORAGE = USER META ONLY / PRODUCT WRITES = 0</strong></p>';

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
        echo '<h2 style="margin-top:0;">Mixed RU Audit — ALL catalog</h2>';
        echo '<p>Start rebuilds the saved report from the beginning. The scan is read-only and processes publish, draft and private WooCommerce products.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_ru_mixed_audit_action" value="audit_start">';
        echo '<p><label><strong>Batch size</strong><br><input type="number" min="1" max="50" name="audit_limit" value="'
            . $this->escapeAttr((string) $state['limit'])
            . '" style="width:180px;"></label></p>';
        echo '<button type="submit" class="button button-primary">Запустить Mixed RU Audit — ALL catalog</button>';
        echo '</form>';

        if ($state['status'] === 'RUNNING') {
            echo '<form method="post" style="margin-top:12px;">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_ru_mixed_audit_action" value="audit_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Mixed RU Audit</button>';
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
            'wp_shop_pm_export_ru_mixed_audit',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="wp_shop_pm_export_ru_mixed_audit">';
        echo '<button type="submit" class="button button-secondary">Export classified CSV</button>';
        echo '</form>';
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-ru-mixed-audit-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_ru_mixed_audit_action" value="audit_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-ru-mixed-audit-next");if(f){f.submit();}},900);';
        echo '</script>';
        echo '<p><em>Следующий пакет Mixed RU Audit запустится автоматически…</em></p>';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(): array
    {
        return [
            'seen' => [],
            'products' => [],
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

        foreach (['seen', 'products'] as $key) {
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
            'limit' => 25,
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
        return max(1, min(50, $limit));
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_ru_mixed_audit',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_ru_mixed_audit',
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
