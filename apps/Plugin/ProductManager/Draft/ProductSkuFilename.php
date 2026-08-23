<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use InvalidArgumentException;

final class ProductSkuFilename
{
    public static function synchronize(
        string $current,
        int $itemId,
        string $salesPage,
        string $version
    ): string {
        $expected = self::build(
            $itemId,
            $salesPage,
            $version
        );
        $current = trim($current);

        if ($current === '' || $current === $expected) {
            return $expected;
        }

        $prefix = self::prefix($itemId, $salesPage);

        if (
            ! str_starts_with($current, $prefix)
            || ! str_ends_with($current, '.zip')
        ) {
            throw new InvalidArgumentException(
                'SKU / ZIP filename does not match the ThemeForest Item ID and Sales Page. '
                . 'Expected canonical prefix: ' . $prefix
            );
        }

        /*
         * A canonical filename with an older version is safe to rebuild.
         * This is the normal case when Envato API version metadata lags
         * behind the public ThemeForest changelog and the editor corrects
         * Version manually before creating the Draft.
         */
        return $expected;
    }

    public static function build(
        int $itemId,
        string $salesPage,
        string $version
    ): string {
        $version = trim($version);

        if ($itemId <= 0) {
            throw new InvalidArgumentException(
                'ThemeForest Item ID must be positive before SKU generation.'
            );
        }

        if (
            $version === ''
            || preg_match('/^[A-Za-z0-9._+-]+$/D', $version) !== 1
        ) {
            throw new InvalidArgumentException(
                'Version contains unsupported characters for the SKU / ZIP filename.'
            );
        }

        return self::prefix($itemId, $salesPage)
            . $version
            . '.zip';
    }

    private static function prefix(
        int $itemId,
        string $salesPage
    ): string {
        $path = parse_url(trim($salesPage), PHP_URL_PATH);

        if (! is_string($path)) {
            throw new InvalidArgumentException(
                'Cannot extract ThemeForest item slug from Sales Page.'
            );
        }

        if (
            preg_match(
                '~/(?:item)/([^/]+)/\d+/?$~',
                $path,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Cannot extract ThemeForest item slug from Sales Page.'
            );
        }

        $slug = strtolower(
            rawurldecode((string) $matches[1])
        );
        $slug = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $slug
        );

        if (! is_string($slug) || trim($slug, '-') === '') {
            throw new InvalidArgumentException(
                'ThemeForest item slug is empty after normalization.'
            );
        }

        return sprintf(
            'themeforest-%d-%s-',
            $itemId,
            trim($slug, '-')
        );
    }
}
