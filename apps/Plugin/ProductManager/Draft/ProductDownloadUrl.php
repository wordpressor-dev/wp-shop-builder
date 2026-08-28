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

        return $uploadsBaseUrl
            . '/woocommerce_uploads/'
            . CatalogProductType::storageFolder($productType)
            . '/'
            . $itemId
            . '/'
            . $skuFilename;
    }
}
