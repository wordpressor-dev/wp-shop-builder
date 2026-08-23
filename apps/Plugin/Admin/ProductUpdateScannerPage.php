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
        $rows = [];
        $scanned = false;

        if ($this->posted('wp_shop_pm_scan_action') === 'scan') {
            ($this->call)(
                'check_admin_referer',
                'wp_shop_pm_update_scan',
                '_wpnonce'
            );
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
        $this->renderForm($offset, $limit);

        if ($scanned) {
            $this->renderSummary($rows, $offset, $limit);
            $this->renderTable($rows);
        }

        echo '</div>';
    }

    private function renderForm(int $offset, int $limit): void
    {
        echo '<div class="postbox" style="max-width:1250px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Scan ThemeForest Products</h2>';
        echo '<p>Use small batches to reduce Envato rate-limit risk. Maximum batch size: 25.</p>';
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

    /**
     * @param list<ProductUpdateScanRow> $rows
     */
    private function renderSummary(
        array $rows,
        int $offset,
        int $limit
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

        echo '<div class="notice notice-info" style="max-width:1210px;padding:10px 14px;">';
        echo '<p><strong>READ ONLY = YES</strong> &nbsp; '
            . 'OFFSET = ' . $this->escape((string) $offset)
            . ' &nbsp; BATCH = ' . $this->escape((string) $limit)
            . ' &nbsp; ROWS = ' . $this->escape((string) count($rows))
            . ' &nbsp; UPDATE_AVAILABLE = ' . $this->escape((string) $counts['UPDATE_AVAILABLE'])
            . ' &nbsp; SAME = ' . $this->escape((string) $counts['SAME'])
            . ' &nbsp; MANUAL_REVIEW = ' . $this->escape((string) $counts['MANUAL_REVIEW'])
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
            echo '<tr><td colspan="8">No ThemeForest products found in this batch.</td></tr>';
        }

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $this->escape((string) $row->productId) . '</td>';
            echo '<td>' . $this->escape($row->title) . '</td>';
            echo '<td>' . $this->escape($row->currentVersion !== '' ? $row->currentVersion : '[empty]') . '</td>';
            echo '<td>' . $this->escape($row->envatoVersion !== '' ? $row->envatoVersion : '[empty]') . '</td>';
            echo '<td>' . $this->escape($row->envatoUpdateDate !== '' ? $row->envatoUpdateDate : '[empty]') . '</td>';
            echo '<td><strong>' . $this->escape($row->status) . '</strong></td>';
            echo '<td>' . $this->escape($row->message) . '</td>';
            echo '<td>';
            $this->renderUpdateAction($row);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
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
        echo '<input type="hidden" name="wp_shop_pm_update_action" value="load_product">';
        echo '<input type="hidden" name="update_product_id" value="'
            . $this->escape((string) $row->productId)
            . '">';
        echo '<button type="submit" class="button button-secondary">Открыть Update Product</button>';
        echo '</form>';
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
            . $this->escape($type)
            . '" name="'
            . $this->escape($name)
            . '" value="'
            . $this->escape($value)
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

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
