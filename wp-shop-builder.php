<?php

/**
 * Plugin Name: WP Shop Builder
 * Plugin URI: https://wp-shop.org
 * Description: Digital Product Platform for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Author: WP Shop
 * Author URI: https://wp-shop.org
 * License: GPL-2.0-or-later
 * Text Domain: wp-shop-builder
 */

declare(strict_types=1);

use WPShop\App\Plugin\Bootstrap;
use WPShop\App\Plugin\Exception\IncompatibleEnvironment;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

$flushRewriteRules = static function (bool $hard): void {
    flush_rewrite_rules($hard);
};

$bootstrap = new Bootstrap(
    null,
    $flushRewriteRules
);

register_activation_hook(
    __FILE__,
    static function () use ($bootstrap): void {
        try {
            $bootstrap->activate();
        } catch (IncompatibleEnvironment $exception) {
            $message = sprintf(
                'WP Shop Builder cannot be activated. %s',
                $exception->getMessage()
            );

            wp_die(
                esc_html($message),
                esc_html__('WP Shop Builder', 'wp-shop-builder'),
                ['back_link' => true]
            );
        }
    }
);

register_deactivation_hook(
    __FILE__,
    static function () use ($bootstrap): void {
        $bootstrap->deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function () use ($bootstrap): void {
        $notice = $bootstrap->boot();

        if ($notice !== null) {
            add_action(
                'admin_notices',
                [$notice, 'render']
            );
        }
    },
    20
);