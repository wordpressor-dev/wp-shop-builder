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

        /*
         * ThemeForest authors can rename an item. Envato then changes the
         * slug used in newly downloaded ZIP filenames while the numeric Item
         * ID remains stable. Treat the Item ID as the product identity and
         * always rebuild the output filename from the current Sales Page.
         */
        $identityPrefix = self::itemIdentityPrefix($itemId);

        if (
            ! str_starts_with($current, $identityPrefix)
            || ! str_ends_with($current, '.zip')
        ) {
            throw new InvalidArgumentException(
                'SKU / ZIP filename does not match the ThemeForest Item ID. '
                . 'Expected item identity prefix: ' . $identityPrefix
            );
        }

        /*
         * A filename for the same ThemeForest Item ID is safe to rebuild.
         * This covers both an older version and an official item/slug rename.
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
                '~/(?:item)/([^/]+)/(\d+)/?$~',
                $path,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Cannot extract ThemeForest item slug from Sales Page.'
            );
        }

        $salesPageItemId = (int) $matches[2];

        if ($salesPageItemId !== $itemId) {
            throw new InvalidArgumentException(
                'ThemeForest Item ID does not match Sales Page Item ID.'
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

        return self::itemIdentityPrefix($itemId)
            . trim($slug, '-')
            . '-';
    }

    private static function itemIdentityPrefix(int $itemId): string
    {
        return sprintf('themeforest-%d-', $itemId);
    }
}
