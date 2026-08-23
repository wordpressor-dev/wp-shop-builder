<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanRow;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanner;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductUpdateScannerPage implements SubmenuPageInterface
{
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
        return 'wp-shop-builder-product-update-scanner';
    }

    public function title(): string
    {
        return 'Update Scanner';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $offset = max(0, (int) $this->posted('scan_offset', '0'));
        $limit = max(1, min(25, (int) $this->posted('scan_limit', '10')));
        $filter = $this->normalizeFilter(
            $this->posted('scan_filter', 'attention')
        );
        $sort = $this->normalizeSort(
            $this->posted('scan_sort', 'envato_date_desc')
        );
        $rows = [];
        $scanned = false;
        $action = $this->posted('wp_shop_pm_scan_action');

        if (in_array($action, ['scan', 'previous', 'next'], true)) {
            ($this->call)(
                'check_admin_referer',
                'wp_shop_pm_update_scan',
                '_wpnonce'
            );

            if ($action === 'previous') {
                $offset = max(0, $offset - $limit);
            } elseif ($action === 'next') {
                $offset += $limit;
            }

            $rows = $this->scanner->scan(
                $offset,
                $limit,
                $this->token()
            );
            $scanned = true;
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Update Scanner</h1>';
        echo '<p>Read-only batch comparison for ThemeForest products. Nothing is written to WooCommerce. Envato metadata is advisory only; verify the public changelog before any update.</p>';
        $this->renderForm($offset, $limit, $filter, $sort);

        if ($scanned) {
            $visibleRows = $this->filterRows($rows, $filter);
            $visibleRows = $this->sortRows($visibleRows, $sort);
            $this->renderSummary(
                $rows,
                $visibleRows,
                $offset,
                $limit,
                $filter,
                $sort
            );
            $this->renderTable($visibleRows);
            $this->renderNavigation(
                $rows,
                $offset,
                $limit,
                $filter,
                $sort
            );
        }

        echo '</div>';
    }

    private function renderForm(
        int $offset,
        int $limit,
        string $filter,
        string $sort
    ): void {
        echo '<div class="postbox" style="max-width:1250px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Scan ThemeForest Products</h2>';
        echo '<p>Use small batches to reduce Envato rate-limit risk. Maximum batch size: 25. By default only products that need attention are shown, newest Envato dates first.</p>';
        echo '<form method="post">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_update_scan',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="wp_shop_pm_scan_action" value="scan">';
        $this->input('Offset', 'scan_offset', (string) $offset, 'number');
        $this->input('Batch size', 'scan_limit', (string) $limit, 'number');
        $this->renderFilterSelect($filter);
        $this->renderSortSelect($sort);
        ($this->call)(
            'submit_button',
            'Проверить пакет обновлений',
            'primary',
            'submit',
            true
        );
        echo '</form>';
        echo '</div>';
    }

    private function renderFilterSelect(string $filter): void
    {
        $options = [
            'attention' => 'Требуют внимания (UPDATE_AVAILABLE + MANUAL_REVIEW)',
            'all' => 'Все статусы',
            'update_available' => 'Только UPDATE_AVAILABLE',
            'manual_review' => 'Только MANUAL_REVIEW',
            'same' => 'Только SAME',
            'errors' => 'Ошибки (LOAD_FAILED + TOKEN_MISSING)',
        ];

        echo '<p><label><strong>Показывать</strong><br>';
        echo '<select name="scan_filter" style="width:360px;max-width:100%;">';

        foreach ($options as $value => $label) {
            echo '<option value="'
                . $this->escapeAttr($value)
                . '"'
                . ($value === $filter ? ' selected' : '')
                . '>'
                . $this->escape($label)
                . '</option>';
        }

        echo '</select></label></p>';
    }

    private function renderSortSelect(string $sort): void
    {
        $options = [
            'envato_date_desc' => 'Envato Date — новые сверху',
            'envato_date_asc' => 'Envato Date — старые сверху',
            'product_asc' => 'Product — A → Z',
            'product_desc' => 'Product — Z → A',
            'status' => 'Status — требуют внимания первыми',
            'id_asc' => 'Product ID — по возрастанию',
            'id_desc' => 'Product ID — по убыванию',
        ];

        echo '<p><label><strong>Сортировать</strong><br>';
        echo '<select name="scan_sort" style="width:360px;max-width:100%;">';

        foreach ($options as $value => $label) {
            echo '<option value="'
                . $this->escapeAttr($value)
                . '"'
                . ($value === $sort ? ' selected' : '')
                . '>'
                . $this->escape($label)
                . '</option>';
        }

        echo '</select></label></p>';
    }

    /**
     * @param list<ProductUpdateScanRow> $rows
     * @param list<ProductUpdateScanRow> $visibleRows
     */
    private function renderSummary(
        array $rows,
        array $visibleRows,
        int $offset,
        int $limit,
        string $filter,
        string $sort
    ): void {
        $counts = [
            'UPDATE_AVAILABLE' => 0,
            'SAME' => 0,
            'MANUAL_REVIEW' => 0,
            'LOAD_FAILED' => 0,
            'TOKEN_MISSING' => 0,
        ];

        foreach ($rows as $row) {
            if (isset($counts[$row->status])) {
                ++$counts[$row->status];
            }
        }

        $first = $rows === [] ? 0 : $offset + 1;
        $last = $offset + count($rows);

        echo '<div class="notice notice-info" style="max-width:1210px;padding:10px 14px;">';
        echo '<p><strong>READ ONLY = YES</strong> &nbsp; '
            . 'OFFSET = ' . $this->escape((string) $offset)
            . ' &nbsp; BATCH = ' . $this->escape((string) $limit)
            . ' &nbsp; ROWS = ' . $this->escape((string) count($rows))
            . ' &nbsp; RANGE = ' . $this->escape((string) $first)
            . '–' . $this->escape((string) $last)
            . ' &nbsp; FILTER = ' . $this->escape($this->filterLabel($filter))
            . ' &nbsp; SORT = ' . $this->escape($this->sortLabel($sort))
            . ' &nbsp; SHOWN = ' . $this->escape((string) count($visibleRows))
            . ' &nbsp; UPDATE_AVAILABLE = '
            . $this->escape((string) $counts['UPDATE_AVAILABLE'])
            . ' &nbsp; SAME = '
            . $this->escape((string) $counts['SAME'])
            . ' &nbsp; MANUAL_REVIEW = '
            . $this->escape((string) $counts['MANUAL_REVIEW'])
            . '</p>';
        echo '</div>';
    }

    /**
     * @param list<ProductUpdateScanRow> $rows
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
                'Status',
                'Note',
                'Action',
            ] as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="8">No rows match the current filter in this batch.</td></tr>';
        }

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $this->escape((string) $row->productId) . '</td>';
            echo '<td>' . $this->escape($row->title) . '</td>';
            echo '<td>'
                . $this->escape(
                    $row->currentVersion !== ''
                        ? $row->currentVersion
                        : '[empty]'
                )
                . '</td>';
            echo '<td>'
                . $this->escape(
                    $row->envatoVersion !== ''
                        ? $row->envatoVersion
                        : '[empty]'
                )
                . '</td>';
            echo '<td>'
                . $this->escape(
                    $row->envatoUpdateDate !== ''
                        ? $row->envatoUpdateDate
                        : '[empty]'
                )
                . '</td>';
            echo '<td><strong>'
                . $this->escape($row->status)
                . '</strong></td>';
            echo '<td>' . $this->escape($row->message) . '</td>';
            echo '<td>';
            $this->renderUpdateAction($row);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param list<ProductUpdateScanRow> $rows
     */
    private function renderNavigation(
        array $rows,
        int $offset,
        int $limit,
        string $filter,
        string $sort
    ): void {
        $hasPrevious = $offset > 0;
        $hasNext = count($rows) === $limit;

        if (! $hasPrevious && ! $hasNext) {
            return;
        }

        echo '<form method="post" style="max-width:1400px;margin:14px 0;display:flex;gap:10px;align-items:center;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_update_scan',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="scan_offset" value="'
            . $this->escapeAttr((string) $offset)
            . '">';
        echo '<input type="hidden" name="scan_limit" value="'
            . $this->escapeAttr((string) $limit)
            . '">';
        echo '<input type="hidden" name="scan_filter" value="'
            . $this->escapeAttr($filter)
            . '">';
        echo '<input type="hidden" name="scan_sort" value="'
            . $this->escapeAttr($sort)
            . '">';

        if ($hasPrevious) {
            echo '<button type="submit" class="button button-secondary" '
                . 'name="wp_shop_pm_scan_action" value="previous">'
                . '← Предыдущий пакет</button>';
        }

        if ($hasNext) {
            echo '<button type="submit" class="button button-primary" '
                . 'name="wp_shop_pm_scan_action" value="next">'
                . 'Следующий пакет →</button>';
        }

        echo '</form>';
    }

    private function renderUpdateAction(ProductUpdateScanRow $row): void
    {
        if (
            $row->status !== 'UPDATE_AVAILABLE'
            && $row->status !== 'MANUAL_REVIEW'
        ) {
            echo '—';

            return;
        }

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
        echo '<input type="hidden" name="wp_shop_pm_update_action" '
            . 'value="load_product">';
        echo '<input type="hidden" name="update_product_id" value="'
            . $this->escapeAttr((string) $row->productId)
            . '">';
        echo '<button type="submit" class="button button-secondary">'
            . 'Открыть Update Product</button>';
        echo '</form>';
    }

    /**
     * @param list<ProductUpdateScanRow> $rows
     * @return list<ProductUpdateScanRow>
     */
    private function filterRows(array $rows, string $filter): array
    {
        if ($filter === 'all') {
            return $rows;
        }

        $visibleRows = [];

        foreach ($rows as $row) {
            $matches = match ($filter) {
                'attention' => in_array(
                    $row->status,
                    ['UPDATE_AVAILABLE', 'MANUAL_REVIEW'],
                    true
                ),
                'update_available' => $row->status === 'UPDATE_AVAILABLE',
                'manual_review' => $row->status === 'MANUAL_REVIEW',
                'same' => $row->status === 'SAME',
                'errors' => in_array(
                    $row->status,
                    ['LOAD_FAILED', 'TOKEN_MISSING'],
                    true
                ),
                default => true,
            };

            if ($matches) {
                $visibleRows[] = $row;
            }
        }

        return $visibleRows;
    }

    /**
     * @param list<ProductUpdateScanRow> $rows
     * @return list<ProductUpdateScanRow>
     */
    private function sortRows(array $rows, string $sort): array
    {
        usort(
            $rows,
            function (ProductUpdateScanRow $left, ProductUpdateScanRow $right) use ($sort): int {
                return match ($sort) {
                    'envato_date_asc' => $this->compareDates(
                        $left->envatoUpdateDate,
                        $right->envatoUpdateDate,
                        false
                    ),
                    'product_asc' => strcasecmp($left->title, $right->title),
                    'product_desc' => strcasecmp($right->title, $left->title),
                    'status' => $this->compareStatuses($left, $right),
                    'id_asc' => $left->productId <=> $right->productId,
                    'id_desc' => $right->productId <=> $left->productId,
                    default => $this->compareDates(
                        $left->envatoUpdateDate,
                        $right->envatoUpdateDate,
                        true
                    ),
                };
            }
        );

        return $rows;
    }

    private function compareDates(
        string $left,
        string $right,
        bool $descending
    ): int {
        if ($left === '' && $right === '') {
            return 0;
        }

        if ($left === '') {
            return 1;
        }

        if ($right === '') {
            return -1;
        }

        return $descending
            ? strcmp($right, $left)
            : strcmp($left, $right);
    }

    private function compareStatuses(
        ProductUpdateScanRow $left,
        ProductUpdateScanRow $right
    ): int {
        $priority = [
            'UPDATE_AVAILABLE' => 0,
            'MANUAL_REVIEW' => 1,
            'LOAD_FAILED' => 2,
            'TOKEN_MISSING' => 3,
            'SAME' => 4,
        ];
        $leftPriority = $priority[$left->status] ?? 99;
        $rightPriority = $priority[$right->status] ?? 99;
        $statusOrder = $leftPriority <=> $rightPriority;

        if ($statusOrder !== 0) {
            return $statusOrder;
        }

        $dateOrder = $this->compareDates(
            $left->envatoUpdateDate,
            $right->envatoUpdateDate,
            true
        );

        if ($dateOrder !== 0) {
            return $dateOrder;
        }

        return strcasecmp($left->title, $right->title);
    }

    private function normalizeFilter(string $filter): string
    {
        $allowed = [
            'attention',
            'all',
            'update_available',
            'manual_review',
            'same',
            'errors',
        ];

        return in_array($filter, $allowed, true)
            ? $filter
            : 'attention';
    }

    private function filterLabel(string $filter): string
    {
        return match ($filter) {
            'all' => 'ALL',
            'update_available' => 'UPDATE_AVAILABLE',
            'manual_review' => 'MANUAL_REVIEW',
            'same' => 'SAME',
            'errors' => 'ERRORS',
            default => 'NEEDS_ATTENTION',
        };
    }

    private function normalizeSort(string $sort): string
    {
        $allowed = [
            'envato_date_desc',
            'envato_date_asc',
            'product_asc',
            'product_desc',
            'status',
            'id_asc',
            'id_desc',
        ];

        return in_array($sort, $allowed, true)
            ? $sort
            : 'envato_date_desc';
    }

    private function sortLabel(string $sort): string
    {
        return match ($sort) {
            'envato_date_asc' => 'ENVATO_DATE_ASC',
            'product_asc' => 'PRODUCT_ASC',
            'product_desc' => 'PRODUCT_DESC',
            'status' => 'STATUS_PRIORITY',
            'id_asc' => 'ID_ASC',
            'id_desc' => 'ID_DESC',
            default => 'ENVATO_DATE_DESC',
        };
    }

    private function input(
        string $label,
        string $name,
        string $value,
        string $type
    ): void {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><input style="width:260px;max-width:100%;" type="'
            . $this->escapeAttr($type)
            . '" name="'
            . $this->escapeAttr($name)
            . '" value="'
            . $this->escapeAttr($value)
            . '"></label></p>';
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
