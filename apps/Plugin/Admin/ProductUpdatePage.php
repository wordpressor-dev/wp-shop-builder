<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductUpdatePage implements SubmenuPageInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductVersionUpdater $updater,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-update';
    }

    public function title(): string
    {
        return 'Update Product';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $fields = $this->defaults();
        $logs = [];
        $success = null;
        $action = $this->posted('wp_shop_pm_update_action');

        if ($action === 'load_product') {
            $this->verifyNonce('wp_shop_pm_load_product');
            $productId = (int) $this->posted('update_product_id');

            try {
                $snapshot = $this->updater->load($productId);
                $fields = [
                    'product_id' => (string) $snapshot->productId,
                    'status' => $snapshot->status,
                    'base_title' => $snapshot->baseTitle,
                    'item_id' => (string) $snapshot->itemId,
                    'current_version' => $snapshot->version,
                    'version' => '',
                    'source_update_date' => $snapshot->sourceUpdateDate,
                    'sales_page' => $snapshot->salesPage,
                    'current_sku' => $snapshot->skuFilename,
                    'current_download_url' => $snapshot->downloadUrl,
                    'download_url' => '',
                ];
                $logs = [
                    'UPDATE LOAD = READY',
                    'PRODUCT ID = ' . $snapshot->productId,
                    'STATUS = ' . $snapshot->status,
                    'CURRENT VERSION = ' . (
                        $snapshot->version !== ''
                            ? $snapshot->version
                            : '[empty]'
                    ),
                    'THEMEFOREST ITEM ID = ' . (
                        $snapshot->itemId > 0
                            ? $snapshot->itemId
                            : 'REVIEW_REQUIRED'
                    ),
                    'CURRENT SKU = ' . (
                        $snapshot->skuFilename !== ''
                            ? $snapshot->skuFilename
                            : '[empty]'
                    ),
                    'NO PRODUCT WRITTEN = YES',
                ];
                $success = true;
            } catch (Throwable $exception) {
                $logs = [
                    'UPDATE LOAD = FAILED',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                    'NO PRODUCT WRITTEN = YES',
                ];
                $success = false;
            }
        } elseif (
            $action === 'preflight_update'
            || $action === 'apply_update'
        ) {
            $this->verifyNonce('wp_shop_pm_update_product');
            $fields = $this->postedFields($fields);
            $data = $this->data($fields);
            $result = $action === 'preflight_update'
                ? $this->updater->preflight($data)
                : $this->updater->update($data);
            $logs = $result->logs;
            $success = $result->success;
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Update Product</h1>';
        echo '<p>Safe version/file update mode for an existing WooCommerce product. Editorial RU/EN content, tags, attributes, image and Advanced Labels are preserved.</p>';
        $this->renderLogs($logs, $success);
        $this->renderLoadForm($fields['product_id']);
        $this->renderUpdateForm($fields);
        echo '</div>';
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'product_id' => '',
            'status' => '',
            'base_title' => '',
            'item_id' => '',
            'current_version' => '',
            'version' => '',
            'source_update_date' => '',
            'sales_page' => '',
            'current_sku' => '',
            'current_download_url' => '',
            'download_url' => '',
        ];
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    private function postedFields(array $defaults): array
    {
        $fields = [];

        foreach (array_keys($defaults) as $key) {
            $fields[$key] = $this->posted('update_' . $key);
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function data(array $fields): ProductUpdateData
    {
        return new ProductUpdateData(
            (int) $fields['product_id'],
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['base_title']
            ),
            (int) $fields['item_id'],
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['current_version']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['version']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['source_update_date']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['sales_page']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['current_sku']
            ),
            (string) ($this->call)(
                'sanitize_text_field',
                $fields['current_sku']
            ),
            (string) ($this->call)(
                'esc_url_raw',
                $fields['download_url']
            )
        );
    }

    private function renderLoadForm(string $productId): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">1. Load Existing Product</h2>';
        echo '<p>Enter the WooCommerce Product ID. Loading is read-only.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_load_product');
        $this->hiddenAction('load_product');
        $this->input(
            'Product ID',
            'update_product_id',
            $productId,
            'number'
        );
        $this->submit('Загрузить товар', 'secondary');
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param array<string, string> $fields
     */
    private function renderUpdateForm(array $fields): void
    {
        echo '<div class="postbox" style="max-width:1100px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">2. Review & Update Version</h2>';
        echo '<p><strong>Safety:</strong> first load the product, then enter the new Version, official update date and new ZIP Download URL. Always run Preflight before Apply.</p>';
        echo '<p><strong>Preserved:</strong> RU/EN descriptions, SureRank content, tags, taxonomies, attributes, featured image and Hit/New labels are not rewritten.</p>';
        echo '<form method="post">';
        $this->nonceField('wp_shop_pm_update_product');
        $this->hiddenAction('apply_update');

        $this->input(
            'Product ID',
            'update_product_id',
            $fields['product_id'],
            'number'
        );
        $this->input(
            'Current status',
            'update_status',
            $fields['status'],
            'text'
        );
        $this->input(
            'Base title',
            'update_base_title',
            $fields['base_title']
        );
        $this->input(
            'ThemeForest Item ID',
            'update_item_id',
            $fields['item_id'],
            'number'
        );
        $this->input(
            'Current Version',
            'update_current_version',
            $fields['current_version']
        );
        $this->input(
            'New Version',
            'update_version',
            $fields['version']
        );
        $this->input(
            'Official update date',
            'update_source_update_date',
            $fields['source_update_date'],
            'date'
        );
        $this->input(
            'Sales Page',
            'update_sales_page',
            $fields['sales_page'],
            'url'
        );
        $this->input(
            'Current SKU / ZIP filename',
            'update_current_sku',
            $fields['current_sku']
        );
        $this->input(
            'Current Download URL (reference only)',
            'update_current_download_url',
            $fields['current_download_url'],
            'url'
        );
        $this->input(
            'New Download URL',
            'update_download_url',
            $fields['download_url'],
            'url'
        );

        echo '<p style="display:flex;gap:10px;align-items:center;">';
        echo '<button type="submit" class="button button-secondary" onclick="this.form.elements[\'wp_shop_pm_update_action\'].value=\'preflight_update\';">Проверить Update без записи</button>';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Обновить существующий товар? Сначала убедитесь, что Preflight = READY.\');">Обновить товар</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * @param list<string> $logs
     */
    private function renderLogs(
        array $logs,
        ?bool $success
    ): void {
        if ($logs === []) {
            return;
        }

        $color = $success === true
            ? '#00a32a'
            : '#d63638';
        echo '<div style="max-width:1100px;background:#fff;border-left:4px solid '
            . $this->escape($color)
            . ';padding:12px 16px;margin:15px 0 20px;">';
        echo '<strong>PRODUCT UPDATE LOG</strong>';
        echo '<pre style="white-space:pre-wrap;margin-bottom:0;">'
            . $this->escape(implode("\n", $logs))
            . '</pre>';
        echo '</div>';
    }

    private function input(
        string $label,
        string $name,
        string $value,
        string $type = 'text'
    ): void {
        echo '<p><label><strong>'
            . $this->escape($label)
            . '</strong><br><input style="width:900px;max-width:100%;" type="'
            . $this->escape($type)
            . '" name="'
            . $this->escape($name)
            . '" value="'
            . $this->escape($value)
            . '"></label></p>';
    }

    private function submit(
        string $label,
        string $class
    ): void {
        ($this->call)(
            'submit_button',
            $label,
            $class,
            'submit',
            true
        );
    }

    private function hiddenAction(string $action): void
    {
        echo '<input type="hidden" name="wp_shop_pm_update_action" value="'
            . $this->escape($action)
            . '">';
    }

    private function nonceField(string $action): void
    {
        ($this->call)(
            'wp_nonce_field',
            $action,
            '_wpnonce',
            true,
            true
        );
    }

    private function verifyNonce(string $action): void
    {
        ($this->call)(
            'check_admin_referer',
            $action,
            '_wpnonce'
        );
    }

    private function posted(string $key): string
    {
        $value = $_POST[$key] ?? '';

        if (! is_string($value)) {
            return '';
        }

        return (string) ($this->call)(
            'wp_unslash',
            $value
        );
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)(
            'esc_html',
            $value
        );
    }
}
