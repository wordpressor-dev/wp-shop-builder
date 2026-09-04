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
         * Envato authors can rename an item. The marketplace slug used in
         * newly downloaded ZIP filenames can therefore change while the
         * numeric Item ID remains stable. Treat marketplace + Item ID as the
         * identity and rebuild the output filename from the current Sales Page.
         */
        $identityPrefix = self::itemIdentityPrefix(
            self::marketplacePrefix($salesPage),
            $itemId
        );

        if (
            ! str_starts_with($current, $identityPrefix)
            || ! str_ends_with($current, '.zip')
        ) {
            throw new InvalidArgumentException(
                'SKU / ZIP filename does not match the Envato marketplace and Item ID. '
                . 'Expected item identity prefix: ' . $identityPrefix
            );
        }

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
                'Envato Item ID must be positive before SKU generation.'
            );
        }

        if (
            $version !== ''
            && preg_match('/^[A-Za-z0-9._+-]+$/D', $version) !== 1
        ) {
            throw new InvalidArgumentException(
                'Version contains unsupported characters for the SKU / ZIP filename.'
            );
        }

        $prefix = self::prefix($itemId, $salesPage);

        if ($version === '') {
            return rtrim($prefix, '-') . '.zip';
        }

        return $prefix
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
                'Cannot extract Envato item slug from Sales Page.'
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
                'Cannot extract Envato item slug from Sales Page.'
            );
        }

        $salesPageItemId = (int) $matches[2];

        if ($salesPageItemId !== $itemId) {
            throw new InvalidArgumentException(
                'Envato Item ID does not match Sales Page Item ID.'
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
                'Envato item slug is empty after normalization.'
            );
        }

        return self::itemIdentityPrefix(
            self::marketplacePrefix($salesPage),
            $itemId
        )
            . trim($slug, '-')
            . '-';
    }

    private static function marketplacePrefix(string $salesPage): string
    {
        $host = parse_url(trim($salesPage), PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        if ($host === 'themeforest.net' || $host === 'www.themeforest.net') {
            return 'themeforest';
        }

        if ($host === 'codecanyon.net' || $host === 'www.codecanyon.net') {
            return 'codecanyon';
        }

        throw new InvalidArgumentException(
            'Sales Page must be a ThemeForest or CodeCanyon item URL.'
        );
    }

    private static function itemIdentityPrefix(
        string $marketplace,
        int $itemId
    ): string {
        return sprintf('%s-%d-', $marketplace, $itemId);
    }
}
