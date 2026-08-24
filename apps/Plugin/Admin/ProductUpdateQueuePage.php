<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductUpdateQueuePage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_update_scan_report_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-update-queue';
    }

    public function title(): string
    {
        return 'Update Queue';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $markedDone = false;

        if ($this->posted('wp_shop_pm_update_queue_action') === 'mark_done') {
            $this->checkNonce();
            $markedDone = $this->markDone(
                (int) $this->posted('report_product_id')
            );
        }

        $report = $this->loadReport();
        $updateRows = $this->rowsByStatus(
            $report['attention'],
            'UPDATE_AVAILABLE'
        );
        $manualRows = $this->rowsByStatus(
            $report['attention'],
            'MANUAL_REVIEW'
        );
        $doneCount = $this->doneCount($report['seen']);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Update Queue</h1>';
        echo '<p>Рабочая очередь после Full Update Scan. Сначала проверяйте официальный ThemeForest changelog, затем открывайте Update Product. Envato metadata остаётся только подсказкой; New Version вводится вручную после проверки changelog.</p>';

        if ($markedDone) {
            echo '<div class="notice notice-success"><p><strong>QUEUE ITEM = DONE</strong></p></div>';
        }

        echo '<div class="notice notice-info" style="max-width:1400px;padding:10px 14px;">';
        echo '<p><strong>QUEUE STORAGE = USER META ONLY</strong> &nbsp; '
            . 'ATTENTION = ' . $this->escape((string) (count($updateRows) + count($manualRows)))
            . ' &nbsp; UPDATE_AVAILABLE = ' . $this->escape((string) count($updateRows))
            . ' &nbsp; MANUAL_REVIEW = ' . $this->escape((string) count($manualRows))
            . ' &nbsp; DONE = ' . $this->escape((string) $doneCount);

        if ($report['updated_at'] !== '') {
            echo ' &nbsp; LAST SAVED = ' . $this->escape($report['updated_at']);
        }

        echo '</p></div>';

        $this->renderNavigationLinks();

        if ($updateRows === [] && $manualRows === []) {
            echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
            echo '<p><em>Рабочая очередь пуста. Запустите Full Update Scan или проверьте накопительный отчёт в Update Scanner.</em></p>';
            echo '</div>';
            echo '</div>';

            return;
        }

        echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">UPDATE_AVAILABLE — '
            . $this->escape((string) count($updateRows))
            . '</h2>';
        echo '<p>Приоритетная очередь. Новые Envato Date показаны сверху. Перед обновлением обязательно откройте ThemeForest и подтвердите версию по публичному changelog.</p>';
        $this->renderTable($updateRows);
        echo '</div>';

        echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;margin-top:22px;">';
        echo '<h2 style="margin-top:0;">MANUAL_REVIEW — '
            . $this->escape((string) count($manualRows))
            . '</h2>';
        echo '<p>Envato metadata недостаточно или выглядит устаревшей. Эти товары нельзя обновлять по Envato-кандидату без ручной проверки официального changelog.</p>';
        $this->renderTable($manualRows);
        echo '</div>';

        echo '</div>';
    }

    private function renderNavigationLinks(): void
    {
        $fullScanUrl = (string) ($this->call)(
            'admin_url',
            'admin.php?page=wp-shop-builder-product-update-full-scan'
        );
        $scannerUrl = (string) ($this->call)(
            'admin_url',
            'admin.php?page=wp-shop-builder-product-update-scanner'
        );

        echo '<p style="display:flex;gap:10px;flex-wrap:wrap;">';
        echo '<a class="button button-secondary" href="'
            . $this->escapeUrl($fullScanUrl)
            . '">Full Update Scan</a>';
        echo '<a class="button button-secondary" href="'
            . $this->escapeUrl($scannerUrl)
            . '">Update Scanner / CSV</a>';
        echo '</p>';
    }

    /**
     * @param list<array<string, int|string>> $rows
     */
    private function renderTable(array $rows): void
    {
        echo '<table class="widefat striped" style="max-width:1400px;">';
        echo '<thead><tr>';

        foreach (
            [
                'ID',
                'Product',
                'Current Version',
                'Envato Version',
                'Envato Date',
                'Note',
                'ThemeForest',
                'Update Product',
                'Queue',
            ] as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }

        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="9">Нет товаров в этой очереди.</td></tr>';
        }

        foreach ($rows as $row) {
            $productId = (int) ($row['productId'] ?? 0);
            echo '<tr>';
            echo '<td>' . $this->escape((string) $productId) . '</td>';
            echo '<td>' . $this->escape((string) ($row['title'] ?? '')) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['currentVersion'] ?? '')) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['envatoVersion'] ?? '')) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['envatoUpdateDate'] ?? '')) . '</td>';
            echo '<td>' . $this->escape((string) ($row['message'] ?? '')) . '</td>';
            echo '<td>';
            $this->renderThemeForestAction($productId);
            echo '</td>';
            echo '<td>';
            $this->renderUpdateAction($productId);
            echo '</td>';
            echo '<td>';
            $this->renderDoneAction($productId);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderThemeForestAction(int $productId): void
    {
        $url = $this->themeForestUrl($productId);

        if ($url === '') {
            echo '—';

            return;
        }

        echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="'
            . $this->escapeUrl($url)
            . '">Открыть ThemeForest ↗</a>';
    }

    private function renderUpdateAction(int $productId): void
    {
        $action = (string) ($this->call)(
            'admin_url',
            'admin.php?page=wp-shop-builder-product-update'
        );

        echo '<form method="post" action="'
            . $this->escapeUrl($action)
            . '" style="margin:0;white-space:nowrap;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_load_product',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="wp_shop_pm_update_action" value="load_product">';
        echo '<input type="hidden" name="update_product_id" value="'
            . $this->escapeAttr((string) $productId)
            . '">';
        echo '<button type="submit" class="button button-primary">Открыть Update Product</button>';
        echo '</form>';
    }

    private function renderDoneAction(int $productId): void
    {
        echo '<form method="post" style="margin:0;white-space:nowrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_update_queue_action" value="mark_done">';
        echo '<input type="hidden" name="report_product_id" value="'
            . $this->escapeAttr((string) $productId)
            . '">';
        echo '<button type="submit" class="button">Обработано</button>';
        echo '</form>';
    }

    private function themeForestUrl(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        $url = trim(
            (string) ($this->call)(
                'get_post_meta',
                $productId,
                'sales_page',
                true
            )
        );

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        if ($host !== 'themeforest.net' && $host !== 'www.themeforest.net') {
            return '';
        }

        return $url;
    }

    /**
     * @param array<int|string, mixed> $storedRows
     * @return list<array<string, int|string>>
     */
    private function rowsByStatus(array $storedRows, string $status): array
    {
        $rows = [];

        foreach ($storedRows as $storedRow) {
            if (! is_array($storedRow)) {
                continue;
            }

            if ((string) ($storedRow['status'] ?? '') !== $status) {
                continue;
            }

            $rows[] = [
                'productId' => (int) ($storedRow['productId'] ?? 0),
                'title' => (string) ($storedRow['title'] ?? ''),
                'currentVersion' => (string) ($storedRow['currentVersion'] ?? ''),
                'envatoVersion' => (string) ($storedRow['envatoVersion'] ?? ''),
                'envatoUpdateDate' => (string) ($storedRow['envatoUpdateDate'] ?? ''),
                'status' => (string) ($storedRow['status'] ?? ''),
                'message' => (string) ($storedRow['message'] ?? ''),
            ];
        }

        usort(
            $rows,
            static function (array $left, array $right): int {
                $leftDate = (string) $left['envatoUpdateDate'];
                $rightDate = (string) $right['envatoUpdateDate'];

                if ($leftDate !== $rightDate) {
                    if ($leftDate === '') {
                        return 1;
                    }

                    if ($rightDate === '') {
                        return -1;
                    }

                    return strcmp($rightDate, $leftDate);
                }

                $titleOrder = strcasecmp(
                    (string) $left['title'],
                    (string) $right['title']
                );

                if ($titleOrder !== 0) {
                    return $titleOrder;
                }

                return (int) $left['productId']
                    <=> (int) $right['productId'];
            }
        );

        return $rows;
    }

    private function markDone(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $report = $this->loadReport();

        if (! isset($report['attention'][$productId])) {
            return false;
        }

        unset(
            $report['attention'][$productId],
            $report['errors'][$productId]
        );
        $report['seen'][$productId] = 'DONE';
        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);

        return true;
    }

    /**
     * @param array<int|string, string> $seen
     */
    private function doneCount(array $seen): int
    {
        $count = 0;

        foreach ($seen as $status) {
            if ($status === 'DONE') {
                ++$count;
            }
        }

        return $count;
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
     * @param array{
     *   seen: array<int|string, string>,
     *   attention: array<int|string, array<string, mixed>>,
     *   errors: array<int|string, array<string, mixed>>,
     *   started_at: string,
     *   updated_at: string
     * } $report
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

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_update_queue',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_update_queue',
            '_wpnonce',
            true,
            true
        );
    }

    private function valueOrEmpty(int|string $value): string
    {
        $value = (string) $value;

        return $value !== '' ? $value : '[empty]';
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
