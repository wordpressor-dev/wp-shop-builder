<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductDownloadUrl
{
    public static function build(
        string $uploadsBaseUrl,
        string $productType,
        int $itemId,
        string $skuFilename
    ): string {
        $uploadsBaseUrl = rtrim(trim($uploadsBaseUrl), '/');
        $skuFilename = trim($skuFilename);

        if (
            $uploadsBaseUrl === ''
            || filter_var($uploadsBaseUrl, FILTER_VALIDATE_URL) === false
            || $itemId <= 0
            || $skuFilename === ''
            || basename($skuFilename) !== $skuFilename
        ) {
            return '';
        }

        $storage = CatalogProductType::storageFolder($productType);
        $vendor = self::vendorFolder($skuFilename);

        return $uploadsBaseUrl
            . '/woocommerce_uploads/'
            . $storage
            . ($vendor !== '' ? '/' . $vendor : '')
            . '/'
            . $itemId
            . '/'
            . $skuFilename;
    }

    public static function rebuildFromCurrent(
        string $currentDownloadUrl,
        string $productType,
        int $itemId,
        string $skuFilename
    ): string {
        $currentDownloadUrl = trim($currentDownloadUrl);

        if ($currentDownloadUrl === '') {
            return '';
        }

        $marker = '/woocommerce_uploads/';
        $position = strpos($currentDownloadUrl, $marker);

        if ($position === false) {
            return '';
        }

        $uploadsBaseUrl = substr($currentDownloadUrl, 0, $position);

        return self::build(
            $uploadsBaseUrl,
            $productType,
            $itemId,
            $skuFilename
        );
    }

    public static function vendorFolder(string $skuFilename): string
    {
        $skuFilename = strtolower(trim($skuFilename));

        if (str_starts_with($skuFilename, 'themeforest-')) {
            return 'Themeforest';
        }

        if (str_starts_with($skuFilename, 'codecanyon-')) {
            return 'Codecanyon';
        }

        return '';
    }
}
