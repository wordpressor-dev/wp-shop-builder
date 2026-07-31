<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

use WPShop\WordPress\Admin\Contracts\AdminPageInterface;
use WPShop\WordPress\Application\Application;

final class DashboardPage implements AdminPageInterface
{
    public function __construct(
        private readonly Application $application
    ) {
    }

    public function slug(): string
    {
        return 'wp-shop-builder';
    }

    public function title(): string
    {
        return 'WP Shop Builder';
    }

    public function capability(): string
    {
        return 'manage_options';
    }

    public function render(): void
    {
        $wordpressVersion = $GLOBALS['wp_version'] ?? 'Unavailable';
        $woocommerceVersion = defined('WC_VERSION')
            ? constant('WC_VERSION')
            : 'Not installed';
        $environment = defined('WP_DEBUG') && (bool) constant('WP_DEBUG')
            ? 'Development'
            : 'Production';

        echo '<div class="wrap">';
        echo '<h1>WP Shop Builder</h1>';
        echo '<table class="widefat striped" style="max-width: 720px">';
        $this->row('Version', $this->application->version());
        $this->row('Environment', $environment);
        $this->row('PHP', PHP_VERSION);
        $this->row('WordPress', (string) $wordpressVersion);
        $this->row('WooCommerce', (string) $woocommerceVersion);
        echo '</table>';
        echo '</div>';
    }

    private function row(string $label, string $value): void
    {
        echo '<tr><th scope="row">';
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo '</th><td>';
        echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        echo '</td></tr>';
    }
}
