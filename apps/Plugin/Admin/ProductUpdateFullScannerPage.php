<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanRow;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanner;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductUpdateFullScannerPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_update_scan_report_v1';
    private const STATE_META_KEY = 'wp_shop_pm_update_full_scan_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductUpdateScanner $scanner,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-update-full-scan';
    }

    public function title(): string
    {
        return 'Full Update Scan';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_full_scan_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;

        if ($action === 'full_start') {
            $this->checkNonce();
            $limit = $this->limit(
                (int) $this->posted('scan_limit', '10')
            );

            if ($this->token() === '') {
                $error = 'Envato token is required before a full catalog scan.';
            } else {
                $this->resetReport();
                $state = $this->newState(
                    $limit,
                    $this->candidateCount()
                );
                $this->saveState($state);
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        } elseif (
            $action === 'full_next'
            || $action === 'full_resume'
        ) {
            $this->checkNonce();

            if ($this->token() === '') {
                $error = 'Envato token is required before continuing the full scan.';
            } elseif ($state['status'] !== 'RUNNING') {
                $error = 'No running full scan was found. Start a new scan.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->reportSummary($report);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Full Update Scan</h1>';
        echo '<p>One-click catalog scan for ThemeForest and CodeCanyon products. The browser processes one bounded batch per request, then automatically opens the next batch. WooCommerce products remain read-only; only the current administrator\'s scan report and progress state are stored.</p>';
        echo '<p><strong>Important:</strong> keep this page open while the automatic scan is running. Envato metadata remains advisory; every actual update still requires public changelog verification in Update Product.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>FULL SCAN ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderProgress($state, $summary);
        $this->renderControls($state);

        if ($state['status'] === 'READY') {
            $scannerUrl = (string) ($this->call)(
                'admin_url',
                'admin.php?page=wp-shop-builder-product-update-scanner'
            );
            echo '<p><a class="button button-primary" href="'
                . $this->escapeUrl($scannerUrl)
                . '">Открыть итоговый Update Scanner отчёт</a></p>';
        }

        if ($autoContinue) {
            $this->renderAutoContinue();
        }

        echo '</div>';
    }

    /**
     * @param array{
     *   status: string,
     *   limit: int,
     *   total: int,
     *   processed: int,
     *   next_offset: int,
     *   started_at: string,
     *   updated_at: string,
     *   error: string
     * } $state
     * @return array{
     *   0: array{
     *     status: string,
     *     limit: int,
     *     total: int,
     *     processed: int,
     *     next_offset: int,
     *     started_at: string,
     *     updated_at: string,
     *     error: string
     *   },
     *   1: string,
     *   2: string
     * }
     */
    private function processNextBatch(array $state): array
    {
        $offset = (int) $state['next_offset'];
        $limit = $this->limit((int) $state['limit']);

        try {
            $rows = $this->scanner->scan(
                $offset,
                $limit,
                $this->token()
            );
            $this->saveReportRows($rows);
        } catch (Throwable $exception) {
            $state['status'] = 'FAILED';
            $state['error'] = $exception->getMessage();
            $state['updated_at'] = $this->currentTime();
            $this->saveState($state);

            return [
                $state,
                '',
                $exception->getMessage(),
            ];
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
            $message = 'FULL SCAN = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'FULL SCAN BATCH = SAVED';
        }

        $this->saveState($state);

        return [$state, $message, ''];
    }

    /**
     * @param array{
     *   status: string,
     *   limit: int,
     *   total: int,
     *   processed: int,
     *   next_offset: int,
     *   started_at: string,
     *   updated_at: string,
     *   error: string
     * } $state
     * @param array{
     *   scanned: int,
     *   attention: int,
     *   update_available: int,
     *   manual_review: int,
     *   done: int,
     *   errors: int
     * } $summary
     */
    private function renderProgress(array $state, array $summary): void
    {
        $total = $state['total'];
        $processed = $state['processed'];
        $percent = $total > 0
            ? min(100, (int) floor(($processed / $total) * 100))
            : ($state['status'] === 'READY' ? 100 : 0);

        echo '<div class="notice notice-info" style="max-width:1250px;padding:10px 14px;">';
        echo '<p><strong>FULL SCAN = '
            . $this->escape($state['status'])
            . '</strong> &nbsp; BATCH = '
            . $this->escape((string) $state['limit'])
            . ' &nbsp; PROCESSED = '
            . $this->escape((string) $processed)
            . ' &nbsp; TOTAL = '
            . $this->escape((string) $total)
            . ' &nbsp; PROGRESS = '
            . $this->escape((string) $percent)
            . '% &nbsp; NEXT OFFSET = '
            . $this->escape((string) $state['next_offset'])
            . '</p>';
        echo '<p><strong>REPORT STORAGE = USER META ONLY</strong> &nbsp; SCANNED UNIQUE = '
            . $this->escape((string) $summary['scanned'])
            . ' &nbsp; ATTENTION = '
            . $this->escape((string) $summary['attention'])
            . ' &nbsp; UPDATE_AVAILABLE = '
            . $this->escape((string) $summary['update_available'])
            . ' &nbsp; MANUAL_REVIEW = '
            . $this->escape((string) $summary['manual_review'])
            . ' &nbsp; DONE = '
            . $this->escape((string) $summary['done'])
            . ' &nbsp; ERRORS = '
            . $this->escape((string) $summary['errors'])
            . '</p>';

        if ($state['started_at'] !== '') {
            echo '<p>STARTED = '
                . $this->escape($state['started_at'])
                . ' &nbsp; LAST SAVED = '
                . $this->escape($state['updated_at'])
                . '</p>';
        }

        echo '</div>';
    }

    /**
     * @param array{
     *   status: string,
     *   limit: int,
     *   total: int,
     *   processed: int,
     *   next_offset: int,
     *   started_at: string,
     *   updated_at: string,
     *   error: string
     * } $state
     */
    private function renderControls(array $state): void
    {
        echo '<div class="postbox" style="max-width:1250px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Full Catalog Scan</h2>';
        echo '<p>A new full scan clears the cumulative report and rebuilds it from the beginning. Default batch size 10 is recommended; maximum is 25.</p>';
        echo '<form method="post" style="margin-bottom:14px;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_full_scan_action" value="full_start">';
        echo '<p><label><strong>Batch size</strong><br><input type="number" min="1" max="25" name="scan_limit" value="'
            . $this->escapeAttr((string) $state['limit'])
            . '" style="width:180px;"></label></p>';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Полный скан очистит текущий накопительный отчёт и соберёт его заново. Продолжить?\');">Запустить полный скан</button>';
        echo '</form>';

        if ($state['status'] === 'RUNNING') {
            echo '<form method="post">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_full_scan_action" value="full_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить полный скан</button>';
            echo '</form>';
            echo '<p><em>Чтобы приостановить автоматическую цепочку, просто закройте или покиньте эту страницу. Накопленные результаты сохранятся; при возвращении нажмите «Продолжить полный скан».</em></p>';
        }

        if ($state['status'] === 'FAILED' && $state['error'] !== '') {
            echo '<p><strong>LAST ERROR:</strong> '
                . $this->escape($state['error'])
                . '</p>';
        }

        echo '</div>';
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-full-scan-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_full_scan_action" value="full_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-full-scan-next");if(f){f.submit();}},1200);';
        echo '</script>';
        echo '<p><em>Следующий пакет запустится автоматически…</em></p>';
    }

    private function candidateCount(): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'sales_page',
                        'value' => 'themeforest.net/item/',
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => 'sales_page',
                        'value' => 'codecanyon.net/item/',
                        'compare' => 'LIKE',
                    ],
                ],
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        return is_array($ids) ? count($ids) : 0;
    }

    /**
     * @return array{
     *   status: string,
     *   limit: int,
     *   total: int,
     *   processed: int,
     *   next_offset: int,
     *   started_at: string,
     *   updated_at: string,
     *   error: string
     * }
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
     * @return array{
     *   status: string,
     *   limit: int,
     *   total: int,
     *   processed: int,
     *   next_offset: int,
     *   started_at: string,
     *   updated_at: string,
     *   error: string
     * }
     */
    private function loadState(): array
    {
        $empty = [
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
            return $empty;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::STATE_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $empty;
        }

        foreach (['status', 'started_at', 'updated_at', 'error'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        foreach (['limit', 'total', 'processed', 'next_offset'] as $key) {
            if (isset($stored[$key])) {
                $empty[$key] = max(0, (int) $stored[$key]);
            }
        }

        $empty['limit'] = $this->limit($empty['limit']);

        return $empty;
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

    /**
     * @param list<ProductUpdateScanRow> $rows
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

            if (in_array($row->status, ['UPDATE_AVAILABLE', 'MANUAL_REVIEW'], true)) {
                $report['attention'][$id] = $this->rowToArray($row);
            } else {
                unset($report['attention'][$id]);
            }

            if (in_array($row->status, ['LOAD_FAILED', 'TOKEN_MISSING'], true)) {
                $report['errors'][$id] = $this->rowToArray($row);
            } else {
                unset($report['errors'][$id]);
            }
        }

        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
    }

    /**
     * @return array{
     *   seen: array<int|string, string>,
     *   attention: array<int|string, array<string, mixed>>,
     *   errors: array<int|string, array<string, mixed>>,
     *   started_at: string,
     *   updated_at: string
     * }
     */
    private function loadReport(): array
    {
        $empty = [
            'seen' => [],
            'attention' => [],
            'errors' => [],
            'started_at' => '',
            'updated_at' => '',
        ];
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $empty;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::REPORT_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $empty;
        }

        foreach (['seen', 'attention', 'errors'] as $key) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        foreach (['started_at', 'updated_at'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        return $empty;
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
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'delete_user_meta',
                $userId,
                self::REPORT_META_KEY
            );
        }
    }

    /**
     * @param array{
     *   seen: array<int|string, string>,
     *   attention: array<int|string, array<string, mixed>>,
     *   errors: array<int|string, array<string, mixed>>,
     *   started_at: string,
     *   updated_at: string
     * } $report
     * @return array{
     *   scanned: int,
     *   attention: int,
     *   update_available: int,
     *   manual_review: int,
     *   done: int,
     *   errors: int
     * }
     */
    private function reportSummary(array $report): array
    {
        $updateAvailable = 0;
        $manualReview = 0;
        $done = 0;

        foreach ($report['attention'] as $row) {
            $status = (string) ($row['status'] ?? '');

            if ($status === 'UPDATE_AVAILABLE') {
                ++$updateAvailable;
            } elseif ($status === 'MANUAL_REVIEW') {
                ++$manualReview;
            }
        }

        foreach ($report['seen'] as $status) {
            if ($status === 'DONE') {
                ++$done;
            }
        }

        return [
            'scanned' => count($report['seen']),
            'attention' => count($report['attention']),
            'update_available' => $updateAvailable,
            'manual_review' => $manualReview,
            'done' => $done,
            'errors' => count($report['errors']),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function rowToArray(ProductUpdateScanRow $row): array
    {
        return [
            'productId' => $row->productId,
            'title' => $row->title,
            'currentVersion' => $row->currentVersion,
            'envatoVersion' => $row->envatoVersion,
            'envatoUpdateDate' => $row->envatoUpdateDate,
            'status' => $row->status,
            'message' => $row->message,
        ];
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_update_full_scan',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_update_full_scan',
            '_wpnonce',
            true,
            true
        );
    }

    private function limit(int $limit): int
    {
        return max(1, min(25, $limit));
    }

    private function currentUserId(): int
    {
        return (int) ($this->call)('get_current_user_id');
    }

    private function currentTime(): string
    {
        return (string) ($this->call)('current_time', 'mysql');
    }

    private function token(): string
    {
        if (defined('WP_SHOP_ENVATO_TOKEN')) {
            $configured = constant('WP_SHOP_ENVATO_TOKEN');

            if (is_string($configured) && trim($configured) !== '') {
                return trim($configured);
            }
        }

        return trim(
            (string) ($this->call)(
                'get_option',
                'wp_shop_envato_personal_token',
                ''
            )
        );
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
