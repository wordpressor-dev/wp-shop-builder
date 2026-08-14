<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductManagerPage implements SubmenuPageInterface
{
    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-manager';
    }

    public function title(): string
    {
        return 'Product Manager';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        if (!current_user_can($this->capability())) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wp-shop-builder'));
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager</h1>';
        echo '<p>Permanent admin module for the validated Product Manager v1.4.2 workflow.</p>';
        echo '<div class="notice notice-info inline"><p>';
        echo '<strong>Migration stage 1:</strong> admin routing is installed. ';
        echo 'The next commit moves Envato Autofill, Draft creation, existing-tags-only logic, and universal RU→EN translation out of WPCode.';
        echo '</p></div>';
        echo '</div>';
    }
}
