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
        $title = mb_strtolower(trim($baseTitle), 'UTF-8');
        $body = mb_strtolower(trim($content), 'UTF-8');
        $url = strtolower(trim($salesPage));
        $combined = trim($title . ' ' . $body);

        if (
            str_contains($combined, 'template kit')
            || str_contains($combined, 'template-kit')
            || str_contains($combined, 'template_kit')
            || str_contains($combined, 'набор шаблонов')
            || str_contains($url, 'template-kit')
            || str_contains($url, 'template_kit')
        ) {
            return self::TEMPLATE_KIT;
        }

        // The product title is stronger evidence than descriptive content.
        // Theme descriptions commonly mention compatible "plugins", and plugin
        // descriptions can mention themes. Never let those secondary mentions
        // override an explicit product type in the title.
        $titleType = self::typeFromText($title);
        if ($titleType !== '') {
            return $titleType;
        }

        $host = parse_url($salesPage, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        if (str_contains($host, 'codecanyon.net')) {
            return self::PLUGIN;
        }

        if (str_contains($host, 'themeforest.net')) {
            return self::THEME;
        }

        return self::typeFromText($body);
    }

    private static function typeFromText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $plugin = self::matchesAny($text, [
            '/(?<![\p{L}\p{N}])wordpress\s+plugin(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])woocommerce\s+plugin(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])plugin\s+for\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])plugin\s+for\s+woocommerce(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])plugin(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])плагин\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])плагин\s+для\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])плагин\s+woocommerce(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])плагин\s+для\s+woocommerce(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])плагин(?![\p{L}\p{N}])/iu',
        ]);
        $theme = self::matchesAny($text, [
            '/(?<![\p{L}\p{N}])wordpress\s+theme(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])theme\s+for\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])woocommerce\s+theme(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])theme(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])тема\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])тема\s+для\s+wordpress(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])тема\s+woocommerce(?![\p{L}\p{N}])/iu',
            '/(?<![\p{L}\p{N}])тема(?![\p{L}\p{N}])/iu',
        ]);

        if ($plugin === $theme) {
            return '';
        }

        return $plugin ? self::PLUGIN : self::THEME;
    }

    /** @param list<string> $patterns */
    private static function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
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
