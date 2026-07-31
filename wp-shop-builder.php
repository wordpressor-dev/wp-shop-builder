<?php

/**
 * Plugin Name: WP Shop Builder
 * Plugin URI: https://wp-shop.org
 * Description: Digital Product Platform for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: WP Shop
 * Author URI: https://wp-shop.org
 * License: GPL-2.0-or-later
 * Text Domain: wp-shop-builder
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

(new \WPShop\App\Plugin\Bootstrap())->boot();