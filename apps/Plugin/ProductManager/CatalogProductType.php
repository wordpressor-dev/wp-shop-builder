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
        string $salesPage
    ): string {
        $title = strtolower(trim($baseTitle));
        $url = strtolower(trim($salesPage));

        if (
            str_contains($title, 'template kit')
            || str_contains($title, 'template-kit')
            || str_contains($url, 'template-kit')
            || str_contains($url, 'template_kit')
        ) {
            return self::TEMPLATE_KIT;
        }

        $host = parse_url($salesPage, PHP_URL_HOST);
        $host = is_string($host)
            ? strtolower($host)
            : '';

        if (str_contains($host, 'codecanyon.net')) {
            return self::PLUGIN;
        }

        return self::THEME;
    }

    public static function categoryLabel(string $type): string
    {
        return match ($type) {
            self::PLUGIN => 'Плагины',
            self::TEMPLATE_KIT => 'Шаблоны',
            default => 'Темы',
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
