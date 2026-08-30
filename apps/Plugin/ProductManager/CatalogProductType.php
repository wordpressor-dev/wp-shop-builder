<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

final class CatalogProductType
{
    public const PLUGIN = 'plugin';
    public const THEME = 'theme';
    public const TEMPLATE_KIT = 'template_kit';

    public static function infer(
        string $baseTitle,
        string $salesPage,
        string $content = ''
    ): string {
        $text = mb_strtolower(trim($baseTitle . ' ' . $content), 'UTF-8');
        $url = strtolower(trim($salesPage));

        if (
            str_contains($text, 'template kit')
            || str_contains($text, 'template-kit')
            || str_contains($text, 'template_kit')
            || str_contains($text, 'набор шаблонов')
            || str_contains($url, 'template-kit')
            || str_contains($url, 'template_kit')
        ) {
            return self::TEMPLATE_KIT;
        }

        foreach ([
            'wordpress plugin',
            'woocommerce plugin',
            'plugin for wordpress',
            'plugin for woocommerce',
            'plugin',
            'плагин wordpress',
            'плагин для wordpress',
            'плагин woocommerce',
            'плагин для woocommerce',
            'плагин',
        ] as $signal) {
            if (str_contains($text, $signal)) {
                return self::PLUGIN;
            }
        }

        foreach ([
            'wordpress theme',
            'theme for wordpress',
            'woocommerce theme',
            'theme',
            'тема wordpress',
            'тема для wordpress',
            'тема woocommerce',
            'тема',
        ] as $signal) {
            if (str_contains($text, $signal)) {
                return self::THEME;
            }
        }

        $host = parse_url($salesPage, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        if (str_contains($host, 'codecanyon.net')) {
            return self::PLUGIN;
        }

        if (str_contains($host, 'themeforest.net')) {
            return self::THEME;
        }

        return '';
    }

    public static function categoryLabel(string $type): string
    {
        return match ($type) {
            self::PLUGIN => 'Плагины',
            self::TEMPLATE_KIT => 'Шаблоны',
            default => 'Темы',
        };
    }

    public static function categorySlug(string $type): string
    {
        return match ($type) {
            self::PLUGIN => 'plugins',
            self::TEMPLATE_KIT => 'templates',
            default => 'themes',
        };
    }

    public static function storageFolder(string $type): string
    {
        return match ($type) {
            self::PLUGIN => 'PLUGINS',
            self::TEMPLATE_KIT => 'TEMPLATES',
            default => 'THEMES',
        };
    }
}
