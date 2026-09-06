<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingReviewV5Row;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingReviewV5Service;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class VendorProductNamingReviewV5Page implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_vendor_naming_review_v5';
    private const STATE_META_KEY = 'wp_shop_pm_vendor_naming_review_state_v5';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorProductNamingReviewV5Service $review,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-vendor-naming-review-v5';
    }

    public function title(): string
    {
        return 'Vendor Naming Review V5';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_vendor_naming_review_v5_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;

        if ($action === 'review_start') {
            $this->checkNonce();
            $this->resetReport();
            $state = $this->newState(
                $this->limit((int) $this->posted('review_limit', '5')),
                $this->review->candidateCount()
            );
            $this->saveState($state);
            [$state, $message, $error] = $this->processNextBatch($state);
            $autoContinue = $error === '' && $state['status'] === 'RUNNING';
        } elseif (
            $action === 'review_next'
            || $action === 'review_resume'
        ) {
            $this->checkNonce();

            if ($state['status'] !== 'RUNNING') {
                $error = 'No running Vendor Naming Review V5 was found.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->summary($report);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Vendor Naming Review V5</h1>';
        echo '<p><strong>V5 / DRY RUN ONLY / PRODUCT WRITES = 0.</strong> Naming decision and package health are deliberately separate. A missing or suspicious ZIP can never by itself force a naming action, and a good Sales Page can still provide naming evidence.</p>';
        echo '<p><strong>Naming Action:</strong> KEEP / RENAME_CANDIDATE / MANUAL_REVIEW. <strong>Package Status:</strong> OK or a separate ZIP/package issue. TranslatePress safety is reported independently before any future write.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>VENDOR NAMING REVIEW V5 ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderProgress($state, $summary);
        $this->renderControls($state);

        if ($state['status'] === 'READY') {
            $this->renderExport();
        }

        $rows = $this->allRows($report['rows']);

        if ($rows !== []) {
            echo '<div class="postbox" style="max-width:1900px;padding:18px 20px;">';
            echo '<h2 style="margin-top:0;">V5 Review Results</h2>';
            echo '<table class="widefat striped"><thead><tr>';
            foreach (
                [
                    'ID',
                    'Current H1',
                    'Sales Page H1',
                    'Sales Page title',
                    'ZIP header',
                    'Package',
                    'TRP',
                    'TRP safety',
                    'Current EN title',
                    'Recommended',
                    'Naming Action',
                    'Confidence',
                    'Reason',
                    'Product',
                ]
                as $heading
            ) {
                echo '<th>' . $this->escape($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $productId = (int) ($row['productId'] ?? 0);
                echo '<tr>';
                echo '<td>' . $this->escape((string) $productId) . '</td>';
                echo '<td>' . $this->escape((string) ($row['currentTitle'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['salesPageH1'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['salesPageTitle'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['zipHeaderName'] ?? '')) . '</td>';
                echo '<td><code>' . $this->escape((string) ($row['packageStatus'] ?? '')) . '</code></td>';
                echo '<td><code>' . $this->escape((string) ($row['translationState'] ?? '')) . '</code></td>';
                echo '<td><code>' . $this->escape((string) ($row['translationSafety'] ?? '')) . '</code></td>';
                echo '<td>' . $this->escape((string) ($row['englishTitle'] ?? '')) . '</td>';
                echo '<td><strong>' . $this->escape((string) ($row['recommendedTitle'] ?? '')) . '</strong></td>';
                echo '<td><strong>' . $this->escape((string) ($row['namingAction'] ?? '')) . '</strong></td>';
                echo '<td>' . $this->escape((string) ($row['confidence'] ?? '')) . '</td>';
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

            echo '</tbody></table></div>';
        }

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
            'wp_shop_pm_export_vendor_naming_review_v5',
            '_wpnonce'
        );

        $report = $this->loadReport();
        $filename = 'wp-shop-vendor-product-naming-review-v5-'
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
                'Current H1',
                'Sales Page',
                'Sales Page Status',
                'Sales Page H1',
                'Sales Page Title',
                'ZIP Header Name',
                'Product Type',
                'Package Status',
                'TranslatePress Title State',
                'Current EN Title',
                'Translation Safety',
                'Recommended Title',
                'Naming Action',
                'Confidence',
                'Reason',
            ],
            ';',
            '"',
            ''
        );

        foreach ($this->allRows($report['rows']) as $row) {
            fputcsv(
                $stream,
                [
                    (string) ($row['productId'] ?? ''),
                    (string) ($row['currentTitle'] ?? ''),
                    (string) ($row['salesPage'] ?? ''),
                    (string) ($row['salesPageStatus'] ?? ''),
                    (string) ($row['salesPageH1'] ?? ''),
                    (string) ($row['salesPageTitle'] ?? ''),
                    (string) ($row['zipHeaderName'] ?? ''),
                    (string) ($row['productType'] ?? ''),
                    (string) ($row['packageStatus'] ?? ''),
                    (string) ($row['translationState'] ?? ''),
                    (string) ($row['englishTitle'] ?? ''),
                    (string) ($row['translationSafety'] ?? ''),
                    (string) ($row['recommendedTitle'] ?? ''),
                    (string) ($row['namingAction'] ?? ''),
                    (string) ($row['confidence'] ?? ''),
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
            $rows = $this->review->scan($offset, $limit);
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
            $message = 'VENDOR NAMING REVIEW V5 = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'VENDOR NAMING REVIEW V5 BATCH = SAVED';
        }

        $this->saveState($state);

        return [$state, $message, ''];
    }

    /**
     * @param list<VendorProductNamingReviewV5Row> $rows
     */
    private function saveReportRows(array $rows): void
    {
        $report = $this->loadReport();

        if ($report['started_at'] === '') {
            $report['started_at'] = $this->currentTime();
        }

        foreach ($rows as $row) {
            $id = $row->productId;
            $report['seen'][$id] = $row->namingAction;
            $report['rows'][$id] = [
                'productId' => $row->productId,
                'currentTitle' => $row->currentTitle,
                'salesPage' => $row->salesPage,
                'salesPageStatus' => $row->salesPageStatus,
                'salesPageH1' => $row->salesPageH1,
                'salesPageTitle' => $row->salesPageTitle,
                'zipHeaderName' => $row->zipHeaderName,
                'productType' => $row->productType,
                'packageStatus' => $row->packageStatus,
                'translationState' => $row->translationState,
                'englishTitle' => $row->englishTitle,
                'translationSafety' => $row->translationSafety,
                'recommendedTitle' => $row->recommendedTitle,
                'namingAction' => $row->namingAction,
                'confidence' => $row->confidence,
                'reason' => $row->reason,
            ];
        }

        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
    }

    /**
     * @param array<string, mixed> $report
     * @return array{scanned:int,keep:int,rename:int,manual:int,package_issues:int}
     */
    private function summary(array $report): array
    {
        $summary = [
            'scanned' => count($report['seen']),
            'keep' => 0,
            'rename' => 0,
            'manual' => 0,
            'package_issues' => 0,
        ];

        foreach ((array) ($report['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $action = (string) ($row['namingAction'] ?? '');
            if ($action === 'KEEP') {
                ++$summary['keep'];
            } elseif ($action === 'RENAME_CANDIDATE') {
                ++$summary['rename'];
            } else {
                ++$summary['manual'];
            }

            if ((string) ($row['packageStatus'] ?? 'OK') !== 'OK') {
                ++$summary['package_issues'];
            }
        }

        return $summary;
    }

    /**
     * @param array<string, int|string> $state
     * @param array{scanned:int,keep:int,rename:int,manual:int,package_issues:int} $summary
     */
    private function renderProgress(array $state, array $summary): void
    {
        $total = (int) $state['total'];
        $processed = (int) $state['processed'];
        $percent = $total > 0
            ? min(100, (int) floor(($processed / $total) * 100))
            : ($state['status'] === 'READY' ? 100 : 0);

        echo '<div class="notice notice-info" style="max-width:1700px;padding:10px 14px;">';
        echo '<p><strong>VENDOR NAMING REVIEW V5 = '
            . $this->escape((string) $state['status'])
            . '</strong> &nbsp; PROCESSED = '
            . $this->escape((string) $processed)
            . ' &nbsp; REVIEW TOTAL = '
            . $this->escape((string) $total)
            . ' &nbsp; PROGRESS = '
            . $this->escape((string) $percent)
            . '%</p>';
        echo '<p><strong>PRODUCT WRITES = 0</strong> &nbsp; KEEP = '
            . $this->escape((string) $summary['keep'])
            . ' &nbsp; RENAME_CANDIDATE = '
            . $this->escape((string) $summary['rename'])
            . ' &nbsp; MANUAL_REVIEW = '
            . $this->escape((string) $summary['manual'])
            . ' &nbsp; PACKAGE ISSUES = '
            . $this->escape((string) $summary['package_issues'])
            . '</p>';
        echo '</div>';
    }

    /**
     * @param array<string, int|string> $state
     */
    private function renderControls(array $state): void
    {
        echo '<div class="postbox" style="max-width:1700px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Vendor Naming Review V5</h2>';
        echo '<p>Recommended batch size is 5. V5 re-checks the 70 current V3 REVIEW rows, fetches Vendor Sales Page evidence, reads TranslatePress title state, and reports package health separately.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_naming_review_v5_action" value="review_start">';
        echo '<p><label><strong>Batch size</strong><br><input type="number" min="1" max="10" name="review_limit" value="'
            . $this->escapeAttr((string) $state['limit'])
            . '" style="width:180px;"></label></p>';
        echo '<button type="submit" class="button button-primary">Запустить Vendor Naming Review V5 — без записи</button>';
        echo '</form>';

        if ($state['status'] === 'RUNNING') {
            echo '<form method="post" style="margin-top:12px;">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_vendor_naming_review_v5_action" value="review_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Vendor Naming Review V5</button>';
            echo '</form>';
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
            'wp_shop_pm_export_vendor_naming_review_v5',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="wp_shop_pm_export_vendor_naming_review_v5">';
        echo '<button type="submit" class="button button-secondary">Export Vendor Naming Review V5 CSV</button>';
        echo '</form>';
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-vendor-naming-review-v5-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_naming_review_v5_action" value="review_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-vendor-naming-review-v5-next");if(f){f.submit();}},1200);';
        echo '</script>';
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
            'limit' => 5,
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
        return max(1, min(10, $limit));
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_vendor_naming_review_v5',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_vendor_naming_review_v5',
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
