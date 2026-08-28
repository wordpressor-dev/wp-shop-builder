<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use InvalidArgumentException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductDownloadUrl;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;

final class ProductUpdateManualCandidateBuilder
{
    public function build(
        int $itemId,
        string $salesPage,
        string $version,
        string $currentDownloadUrl
    ): ProductUpdateSuggestion {
        $version = trim($version);
        $salesPage = trim($salesPage);
        $productType = CatalogProductType::infer('', $salesPage);

        if ($itemId <= 0) {
            throw new InvalidArgumentException(
                'ThemeForest Item ID is required before manual candidate preparation.'
            );
        }

        if (
            $version === ''
            && $productType !== CatalogProductType::TEMPLATE_KIT
        ) {
            throw new InvalidArgumentException(
                'New Version is required before manual candidate preparation.'
            );
        }

        $salesPageItemId = $this->itemIdFromSalesPage($salesPage);

        if ($salesPageItemId <= 0) {
            throw new InvalidArgumentException(
                'Cannot extract ThemeForest Item ID from Sales Page.'
            );
        }

        if ($salesPageItemId !== $itemId) {
            throw new InvalidArgumentException(
                'ThemeForest Item ID does not match the Sales Page.'
            );
        }

        $skuFilename = ProductSkuFilename::build(
            $itemId,
            $salesPage,
            $version
        );

        return new ProductUpdateSuggestion(
            $version,
            '',
            $skuFilename,
            ProductDownloadUrl::rebuildFromCurrent(
                $currentDownloadUrl,
                $productType,
                $itemId,
                $skuFilename
            )
        );
    }

    private function itemIdFromSalesPage(string $salesPage): int
    {
        $path = parse_url($salesPage, PHP_URL_PATH);

        if (! is_string($path)) {
            return 0;
        }

        if (
            preg_match('~/item/[^/]+/(\d+)/?$~', $path, $matches)
            !== 1
        ) {
            return 0;
        }

        return (int) $matches[1];
    }
}
