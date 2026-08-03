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

use WPShop\App\Plugin\PluginServiceProvider;
use WPShop\App\Plugin\Bootstrap;
use WPShop\App\Plugin\Database\WordPressDatabaseConnection;
use WPShop\App\Plugin\Database\WordPressSchemaManager;
use WPShop\App\Plugin\Exception\IncompatibleEnvironment;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\InstallationManager;
use WPShop\App\Plugin\Installation\MigrationRegistry;
use WPShop\App\Plugin\Installation\MigrationRunner;
use WPShop\App\Plugin\Installation\Migrations\CreateInitialSchema;
use WPShop\App\Plugin\Installation\OptionInstalledVersionStore;
use WPShop\App\Plugin\Plugin;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

global $wpdb;

$getOption = (static fn(string $name, mixed $default): mixed => get_option($name, $default));

$updateOption = (static fn(string $name, mixed $value, bool $autoload): bool => update_option(
    $name,
    $value,
    $autoload
));

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

$insert = static function (
    string $table,
    array $data,
    array $formats
) use ($wpdb): int {
    $result = $wpdb->insert(
        $table,
        $data,
        $formats
    );

    if ($result === false) {
        throw new RuntimeException(
            sprintf(
                'WordPress database insert failed: %s',
                $wpdb->last_error
            )
        );
    }

    return (int) $wpdb->insert_id;
};

$prepare = static function (
    string $sql,
    array $parameters
) use ($wpdb): string {
    $prepared = $wpdb->prepare(
        $sql,
        $parameters
    );

    if (! is_string($prepared)) {
        throw new UnexpectedValueException(
            'WordPress database query preparation failed.'
        );
    }

    return $prepared;
};

$fetchOne = static function (
    string $sql
) use ($wpdb): ?array {
    $row = $wpdb->get_row(
        $sql,
        ARRAY_A
    );

    if ($row === null) {
        if ($wpdb->last_error !== '') {
            throw new RuntimeException(
                sprintf(
                    'WordPress database query failed: %s',
                    $wpdb->last_error
                )
            );
        }

        return null;
    }

    if (! is_array($row)) {
        throw new UnexpectedValueException(
            'WordPress database query returned an invalid row.'
        );
    }

    return $row;
};

$update = static function (
    string $table,
    array $data,
    array $where,
    array $formats,
    array $whereFormats
) use ($wpdb): int {
    $result = $wpdb->update(
        $table,
        $data,
        $where,
        $formats,
        $whereFormats
    );

    if ($result === false) {
        throw new RuntimeException(
            sprintf(
                'WordPress database update failed: %s',
                $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'Unknown database error.'
            )
        );
    }

    return $result;
};

$fetchAll = static function (
    string $sql
) use ($wpdb): array {
    $rows = $wpdb->get_results(
        $sql,
        ARRAY_A
    );

    if ($rows === null) {
        if ($wpdb->last_error !== '') {
            throw new RuntimeException(
                sprintf(
                    'WordPress database collection query failed: %s',
                    $wpdb->last_error
                )
            );
        }

        return [];
    }

    if (! is_array($rows)) {
        throw new UnexpectedValueException(
            'WordPress database collection query returned an invalid result.'
        );
    }

    foreach ($rows as $row) {
        if (! is_array($row)) {
            throw new UnexpectedValueException(
                'WordPress database collection query returned an invalid row.'
            );
        }
    }

    return array_values($rows);
};

$fetchInteger = static function (
    string $sql
) use ($wpdb): int|string|null {
    $value = $wpdb->get_var($sql);

    if ($value === null) {
        if ($wpdb->last_error !== '') {
            throw new RuntimeException(
                sprintf(
                    'WordPress database integer query failed: %s',
                    $wpdb->last_error
                )
            );
        }

        return null;
    }

    if (
        ! is_int($value)
        && ! is_string($value)
    ) {
        throw new UnexpectedValueException(
            'WordPress database integer query returned an invalid result.'
        );
    }

    return $value;
};

$database = new WordPressDatabaseConnection(
    $insert,
    $prepare,
    $fetchOne,
    $update,
    $fetchAll,
    $fetchInteger
);

$blueprintsTable = $schema->table(
    CreateInitialSchema::BLUEPRINTS_TABLE
);

$releasesTable = $schema->table(
    CreateInitialSchema::RELEASES_TABLE
);

$manifestsTable = $schema->table(
    CreateInitialSchema::MANIFESTS_TABLE
);

$uuidGenerator = (static fn(): string => wp_generate_uuid4());

$clock = (static fn(): DateTimeImmutable => current_datetime());

$query = (
    static fn(string $sql): int|bool =>
        $wpdb->query($sql)
);

$serviceProviderFactory = (
    static fn(ContainerInterface $container): ServiceProviderInterface =>
        new PluginServiceProvider(
            $container,
            $database,
            $blueprintsTable,
            $releasesTable,
            $manifestsTable,
            $uuidGenerator,
            $clock,
            $query
        )
);

$plugin = new Plugin(
    $serviceProviderFactory
);

$flushRewriteRules = static function (bool $hard): void {
    flush_rewrite_rules($hard);
};

$bootstrap = new Bootstrap(
    null,
    $flushRewriteRules,
    $installation,
    $plugin
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

        if ($notice instanceof \WPShop\App\Plugin\Admin\AdminNoticeInterface) {
            add_action(
                'admin_notices',
                $notice->render(...)
            );
        }
    },
    20
);
