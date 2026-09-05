<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use InvalidArgumentException;

final class ProductVendorSkuFilename
{
    public static function build(
        string $originalFilename,
        string $baseTitle,
        string $version
    ): string {
        $originalFilename = trim($originalFilename);
        $baseTitle = trim($baseTitle);
        $version = trim($version);

        if (
            $version === ''
            || preg_match('/^[A-Za-z0-9._+-]+$/', $version) !== 1
        ) {
            throw new InvalidArgumentException(
                'Vendor version contains unsupported characters.'
            );
        }

        if (
            $originalFilename !== ''
            && basename($originalFilename) === $originalFilename
            && strtolower((string) pathinfo(
                $originalFilename,
                PATHINFO_EXTENSION
            )) === 'zip'
        ) {
            $pattern = '/(?<![A-Za-z0-9])'
                . preg_quote($version, '/')
                . '(?![A-Za-z0-9])/';

            if (preg_match_all($pattern, $originalFilename) === 1) {
                return $originalFilename;
            }
        }

        $slug = strtolower($baseTitle);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = is_string($slug) ? trim($slug, '-') : '';

        if ($slug === '') {
            throw new InvalidArgumentException(
                'Vendor product title cannot build a safe SKU.'
            );
        }

        return $slug . '-' . $version . '.zip';
    }

    public static function synchronize(
        string $currentSku,
        string $currentVersion,
        string $newVersion
    ): string {
        $currentSku = trim($currentSku);
        $currentVersion = trim($currentVersion);
        $newVersion = trim($newVersion);

        if (
            $currentSku === ''
            || basename($currentSku) !== $currentSku
            || strtolower((string) pathinfo(
                $currentSku,
                PATHINFO_EXTENSION
            )) !== 'zip'
        ) {
            throw new InvalidArgumentException(
                'Vendor SKU must be an existing ZIP filename.'
            );
        }

        if ($currentVersion === '') {
            throw new InvalidArgumentException(
                'Current vendor version is required for safe SKU sync.'
            );
        }

        if (
            $newVersion === ''
            || preg_match('/^[A-Za-z0-9._+-]+$/', $newVersion) !== 1
        ) {
            throw new InvalidArgumentException(
                'New vendor version contains unsupported characters.'
            );
        }

        $pattern = '/(?<![A-Za-z0-9])'
            . preg_quote($currentVersion, '/')
            . '(?![A-Za-z0-9])/';

        $matches = preg_match_all(
            $pattern,
            $currentSku
        );

        if ($matches !== 1) {
            throw new InvalidArgumentException(
                'Current vendor version was not found exactly once in SKU. '
                . 'Review filename manually before update.'
            );
        }

        $updated = preg_replace(
            $pattern,
            $newVersion,
            $currentSku,
            1
        );

        if (! is_string($updated)) {
            throw new InvalidArgumentException(
                'Vendor SKU version replacement failed.'
            );
        }

        if (
            basename($updated) !== $updated
            || strtolower((string) pathinfo(
                $updated,
                PATHINFO_EXTENSION
            )) !== 'zip'
        ) {
            throw new InvalidArgumentException(
                'Generated vendor SKU is invalid.'
            );
        }

        return $updated;
    }
}
