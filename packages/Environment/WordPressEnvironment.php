<?php

declare(strict_types=1);

namespace WPShop\Environment;

use WPShop\Environment\Contracts\WordPressEnvironmentInterface;

final class WordPressEnvironment implements WordPressEnvironmentInterface
{
    public function version(): string
    {
        if (!function_exists('get_bloginfo')) {
            return 'Unavailable';
        }

        return (string) get_bloginfo('version');
    }

    public function locale(): string
    {
        if (!function_exists('get_locale')) {
            return 'Unavailable';
        }

        return (string) get_locale();
    }

    public function timezone(): string
    {
        if (function_exists('wp_timezone_string')) {
            $timezone = (string) wp_timezone_string();

            if ($timezone !== '') {
                return $timezone;
            }
        }

        return date_default_timezone_get();
    }

    public function isMultisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }

    public function isDebug(): bool
    {
        return defined('WP_DEBUG') && constant('WP_DEBUG') === true;
    }
}
