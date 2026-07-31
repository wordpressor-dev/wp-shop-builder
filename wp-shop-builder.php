<?php

/**
 * Plugin Name: WP Shop Builder
 * Plugin URI: https://wp-shop.org
 * Description: Digital Product Platform for WordPress.
 * Version: 0.2.0
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
use WPShop\App\Plugin\Database\WordPressSchemaManager;
use WPShop\App\Plugin\Exception\IncompatibleEnvironment;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\InstallationManager;
use WPShop\App\Plugin\Installation\MigrationRegistry;
use WPShop\App\Plugin\Installation\MigrationRunner;
use WPShop\App\Plugin\Installation\OptionInstalledVersionStore;
use WPShop\App\Plugin\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

global $wpdb;

$getOption = static function (
    string $name,
    mixed $default
): mixed {
    return get_option($name, $default);
};

$updateOption = static function (
    string $name,
    mixed $value,
    bool $autoload
): bool {
    return update_option(
        $name,
        $value,
        $autoload
    );
};

$applySchema = static function (string $sql): void {
    require_once ABSPATH
        . 'wp-admin/includes/upgrade.php';

    dbDelta($sql);
};

$schema = new WordPressSchemaManager(
    $wpdb->prefix,
    $wpdb->get_charset_collate(),
    $applySchema
);

$registry = MigrationRegistry::create($schema);

$versionStore = new OptionInstalledVersionStore(
    $getOption,
    $updateOption
);

$migrations = new MigrationRunner(
    $registry->all()
);

$installation = new InstallationManager(
    $versionStore,
    $migrations,
    Plugin::VERSION
);

$flushRewriteRules = static function (bool $hard): void {
    flush_rewrite_rules($hard);
};

$bootstrap = new Bootstrap(
    null,
    $flushRewriteRules,
    $installation
);

register_activation_hook(
    __FILE__,
    static function () use ($bootstrap): void {
        try {
            $bootstrap->activate();
        } catch (
            IncompatibleEnvironment | InstallationFailed $exception
        ) {
            $message = sprintf(
                'WP Shop Builder cannot be activated. %s',
                $exception->getMessage()
            );

            wp_die(
                esc_html($message),
                esc_html__(
                    'WP Shop Builder',
                    'wp-shop-builder'
                ),
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
