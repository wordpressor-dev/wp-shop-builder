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
        $filter = $this->normalizeFilter(
            $this->posted('queue_filter', 'update_available')
        );
        $search = trim($this->posted('queue_search'));
        $perPage = $this->normalizePerPage(
            (int) $this->posted('queue_per_page', '25')
        );
        $page = max(1, (int) $this->posted('queue_page', '1'));
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
        $doneRows = $this->doneRows($report['seen']);
        $doneCount = $this->doneCount($report['seen']);
        $viewRows = $this->rowsForFilter(
            $updateRows,
            $manualRows,
            $doneRows,
            $filter
        );
        $matchingRows = $this->searchRows($viewRows, $search);
        $matchingCount = count($matchingRows);
        $totalPages = max(1, (int) ceil($matchingCount / $perPage));
        $page = min($page, $totalPages);
        $pageRows = array_slice(
            $matchingRows,
            ($page - 1) * $perPage,
            $perPage
        );

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Update Queue</h1>';
        echo '<p>Рабочая очередь после Full Update Scan. Сначала проверяйте официальный Envato changelog, затем открывайте Update Product. Envato metadata остаётся только подсказкой; New Version вводится вручную после проверки changelog.</p>';

        if ($markedDone) {
            echo '<div class="notice notice-success"><p><strong>QUEUE ITEM = DONE</strong></p></div>';
        }

        echo '<div class="notice notice-info" style="max-width:1400px;padding:10px 14px;">';
        echo '<p><strong>QUEUE STORAGE = USER META ONLY</strong> &nbsp; '
            . 'ATTENTION = ' . $this->escape((string) (count($updateRows) + count($manualRows)))
            . ' &nbsp; UPDATE_AVAILABLE = ' . $this->escape((string) count($updateRows))
            . ' &nbsp; MANUAL_REVIEW = ' . $this->escape((string) count($manualRows))
            . ' &nbsp; DONE = ' . $this->escape((string) $doneCount)
            . ' &nbsp; VIEW = ' . $this->escape($this->filterLabel($filter))
            . ' &nbsp; MATCHES = ' . $this->escape((string) $matchingCount)
            . ' &nbsp; PAGE = ' . $this->escape((string) $page)
            . '/' . $this->escape((string) $totalPages)
            . ' &nbsp; PER PAGE = ' . $this->escape((string) $perPage);

        if ($report['updated_at'] !== '') {
            echo ' &nbsp; LAST SAVED = ' . $this->escape($report['updated_at']);
        }

        echo '</p></div>';

        $this->renderNavigationLinks();
        $this->renderControls($filter, $search, $perPage);

        if ($updateRows === [] && $manualRows === [] && $doneRows === []) {
            echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
            echo '<p><em>Рабочая очередь пуста. Запустите Full Update Scan или проверьте накопительный отчёт в Update Scanner.</em></p>';
            echo '</div>';
            echo '</div>';

            return;
        }

        echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Рабочая очередь — '
            . $this->escape($this->filterLabel($filter))
            . '</h2>';
        echo '<p>Новые Envato Date показаны сверху. Поиск работает по Product ID и названию товара. Перед обновлением обязательно откройте Envato и подтвердите версию по публичному changelog.</p>';

        if ($matchingRows === []) {
            echo '<p><em>По текущему фильтру и поиску ничего не найдено.</em></p>';
        } else {
            $this->renderTable(
                $pageRows,
                $filter,
                $search,
                $perPage,
                $page
            );
            $this->renderPagination(
                $page,
                $totalPages,
                $matchingCount,
                $filter,
                $search,
                $perPage
            );
        }

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

    private function renderControls(
        string $filter,
        string $search,
        int $perPage
    ): void {
        echo '<div class="postbox" style="max-width:1400px;padding:14px 18px;">';
        echo '<form method="post" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;">';
        echo '<input type="hidden" name="wp_shop_pm_update_queue_action" value="browse">';
        echo '<input type="hidden" name="queue_page" value="1">';

        echo '<label><strong>Очередь</strong><br>';
        echo '<select name="queue_filter" style="min-width:230px;">';

        foreach (
            [
                'update_available' => 'UPDATE_AVAILABLE',
                'manual_review' => 'MANUAL_REVIEW',
                'done' => 'DONE',
                'all' => 'ALL ATTENTION',
            ] as $value => $label
        ) {
            echo '<option value="'
                . $this->escapeAttr($value)
                . '"'
                . ($filter === $value ? ' selected' : '')
                . '>'
                . $this->escape($label)
                . '</option>';
        }

        echo '</select></label>';

        echo '<label><strong>Поиск Product / ID</strong><br>';
        echo '<input type="search" name="queue_search" value="'
            . $this->escapeAttr($search)
            . '" placeholder="Например: Cardioly или 4087" style="width:300px;max-width:100%;">';
        echo '</label>';

        echo '<label><strong>На странице</strong><br>';
        echo '<select name="queue_per_page">';

        foreach ([25, 50, 100] as $value) {
            echo '<option value="'
                . $this->escapeAttr((string) $value)
                . '"'
                . ($perPage === $value ? ' selected' : '')
                . '>'
                . $this->escape((string) $value)
                . '</option>';
        }

        echo '</select></label>';
        echo '<button type="submit" class="button button-primary">Применить</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param list<array<string, int|string>> $rows
     */
    private function renderTable(
        array $rows,
        string $filter,
        string $search,
        int $perPage,
        int $page
    ): void {
        echo '<table class="widefat striped" style="max-width:1400px;">';
        echo '<thead><tr>';

        foreach (
            [
                'ID',
                'Product',
                'Current Version',
                'Envato Version',
                'Envato Date',
                'Status',
                'Note',
                'Envato',
                'Update Product',
                'Queue',
            ] as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }

        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="10">Нет товаров в этой очереди.</td></tr>';
        }

        foreach ($rows as $row) {
            $productId = (int) $row['productId'];
            echo '<tr>';
            echo '<td>' . $this->escape((string) $productId) . '</td>';
            echo '<td>' . $this->escape((string) $row['title']) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['currentVersion'])) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['envatoVersion'])) . '</td>';
            echo '<td>' . $this->escape($this->valueOrEmpty($row['envatoUpdateDate'])) . '</td>';
            echo '<td><strong>' . $this->escape((string) $row['status']) . '</strong></td>';
            echo '<td>' . $this->escape((string) $row['message']) . '</td>';
            echo '<td>';
            $this->renderEnvatoAction($productId);
            echo '</td>';
            echo '<td>';
            $this->renderUpdateAction($productId);
            echo '</td>';
            echo '<td>';

            if ($filter === 'done') {
                echo '<strong>DONE</strong>';
            } else {
                $this->renderDoneAction(
                    $productId,
                    $filter,
                    $search,
                    $perPage,
                    $page
                );
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderPagination(
        int $page,
        int $totalPages,
        int $matchingCount,
        string $filter,
        string $search,
        int $perPage
    ): void {
        if ($totalPages <= 1) {
            return;
        }

        echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px;">';

        if ($page > 1) {
            $this->renderPageButton(
                '← Предыдущая',
                $page - 1,
                $filter,
                $search,
                $perPage
            );
        }

        echo '<span><strong>Страница '
            . $this->escape((string) $page)
            . ' из '
            . $this->escape((string) $totalPages)
            . '</strong> &nbsp; Найдено: '
            . $this->escape((string) $matchingCount)
            . '</span>';

        if ($page < $totalPages) {
            $this->renderPageButton(
                'Следующая →',
                $page + 1,
                $filter,
                $search,
                $perPage
            );
        }

        echo '</div>';
    }

    private function renderPageButton(
        string $label,
        int $page,
        string $filter,
        string $search,
        int $perPage
    ): void {
        echo '<form method="post" style="margin:0;">';
        echo '<input type="hidden" name="wp_shop_pm_update_queue_action" value="browse">';
        $this->hiddenQueueState($filter, $search, $perPage, $page);
        echo '<button type="submit" class="button button-secondary">'
            . $this->escape($label)
            . '</button>';
        echo '</form>';
    }

    private function renderEnvatoAction(int $productId): void
    {
        $url = $this->envatoUrl($productId);

        if ($url === '') {
            echo '—';

            return;
        }

        echo '<a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="'
            . $this->escapeUrl($url)
            . '">Открыть Envato ↗</a>';
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

    private function renderDoneAction(
        int $productId,
        string $filter,
        string $search,
        int $perPage,
        int $page
    ): void {
        echo '<form method="post" style="margin:0;white-space:nowrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_update_queue_action" value="mark_done">';
        echo '<input type="hidden" name="report_product_id" value="'
            . $this->escapeAttr((string) $productId)
            . '">';
        $this->hiddenQueueState($filter, $search, $perPage, $page);
        echo '<button type="submit" class="button">Обработано</button>';
        echo '</form>';
    }

    private function hiddenQueueState(
        string $filter,
        string $search,
        int $perPage,
        int $page
    ): void {
        echo '<input type="hidden" name="queue_filter" value="'
            . $this->escapeAttr($filter)
            . '">';
        echo '<input type="hidden" name="queue_search" value="'
            . $this->escapeAttr($search)
            . '">';
        echo '<input type="hidden" name="queue_per_page" value="'
            . $this->escapeAttr((string) $perPage)
            . '">';
        echo '<input type="hidden" name="queue_page" value="'
            . $this->escapeAttr((string) $page)
            . '">';
    }

    private function envatoUrl(int $productId): string
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

        if (
            ! in_array(
                $host,
                [
                    'themeforest.net',
                    'www.themeforest.net',
                    'codecanyon.net',
                    'www.codecanyon.net',
                ],
                true
            )
        ) {
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

        return $this->sortRows($rows);
    }

    /**
     * @param list<array<string, int|string>> $updateRows
     * @param list<array<string, int|string>> $manualRows
     * @param list<array<string, int|string>> $doneRows
     * @return list<array<string, int|string>>
     */
    private function rowsForFilter(
        array $updateRows,
        array $manualRows,
        array $doneRows,
        string $filter
    ): array {
        if ($filter === 'manual_review') {
            return $manualRows;
        }

        if ($filter === 'done') {
            return $doneRows;
        }

        if ($filter === 'all') {
            return $this->sortRows(array_merge($updateRows, $manualRows));
        }

        return $updateRows;
    }

    /**
     * @param array<int|string, string> $seen
     * @return list<array<string, int|string>>
     */
    private function doneRows(array $seen): array
    {
        $rows = [];

        foreach ($seen as $storedProductId => $status) {
            if ($status !== 'DONE') {
                continue;
            }

            $productId = (int) $storedProductId;

            if ($productId <= 0) {
                continue;
            }

            $title = trim((string) ($this->call)(
                'get_post_field',
                'post_title',
                $productId
            ));
            $version = trim((string) ($this->call)(
                'get_post_meta',
                $productId,
                'attr_version_value',
                true
            ));
            $sourceUpdateDate = trim((string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_source_update_date',
                true
            ));

            $rows[] = [
                'productId' => $productId,
                'title' => $title !== '' ? $title : 'Product #' . $productId,
                'currentVersion' => $version,
                'envatoVersion' => $version,
                'envatoUpdateDate' => $sourceUpdateDate,
                'status' => 'DONE',
                'message' => 'Processed / updated.',
            ];
        }

        return $this->sortRows($rows);
    }

    /**
     * @param list<array<string, int|string>> $rows
     * @return list<array<string, int|string>>
     */
    private function searchRows(array $rows, string $search): array
    {
        if ($search === '') {
            return $rows;
        }

        $matching = [];

        foreach ($rows as $row) {
            $productId = (string) $row['productId'];
            $title = (string) $row['title'];

            if (
                stripos($productId, $search) !== false
                || stripos($title, $search) !== false
            ) {
                $matching[] = $row;
            }
        }

        return $matching;
    }

    /**
     * @param list<array<string, int|string>> $rows
     * @return list<array<string, int|string>>
     */
    private function sortRows(array $rows): array
    {
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

    private function normalizeFilter(string $filter): string
    {
        if (in_array($filter, ['update_available', 'manual_review', 'done', 'all'], true)) {
            return $filter;
        }

        return 'update_available';
    }

    private function filterLabel(string $filter): string
    {
        return match ($filter) {
            'manual_review' => 'MANUAL_REVIEW',
            'done' => 'DONE',
            'all' => 'ALL_ATTENTION',
            default => 'UPDATE_AVAILABLE',
        };
    }

    private function normalizePerPage(int $perPage): int
    {
        if (in_array($perPage, [25, 50, 100], true)) {
            return $perPage;
        }

        return 25;
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
