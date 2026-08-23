<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;

final class ProductUpdateEnvatoAdvisor
{
    public function __construct(
        private readonly EnvatoClientInterface $envatoClient
    ) {
    }

    public function suggest(
        ProductUpdateSnapshot $snapshot,
        string $token
    ): ProductUpdateSuggestion {
        if (trim($snapshot->salesPage) === '') {
            throw new RuntimeException(
                'Sales Page is required before Envato update lookup.'
            );
        }

        $item = $this->envatoClient->fetch(
            $snapshot->salesPage,
            $token
        );

        if (
            $snapshot->itemId > 0
            && $item->itemId !== $snapshot->itemId
        ) {
            throw new RuntimeException(
                'Envato Item ID does not match the loaded product.'
            );
        }

        $version = trim($item->version);
        $updateDate = trim($item->updatedDate);
        $skuFilename = '';
        $downloadUrl = '';

        if ($version !== '') {
            $skuFilename = ProductSkuFilename::build(
                $item->itemId,
                $snapshot->salesPage,
                $version
            );
            $downloadUrl = $this->replaceFilename(
                $snapshot->downloadUrl,
                $skuFilename
            );
        }

        return new ProductUpdateSuggestion(
            $version,
            $updateDate,
            $skuFilename,
            $downloadUrl
        );
    }

    private function replaceFilename(
        string $currentUrl,
        string $skuFilename
    ): string {
        $currentUrl = trim($currentUrl);

        if ($currentUrl === '' || $skuFilename === '') {
            return '';
        }

        $position = strrpos($currentUrl, '/');

        if ($position === false) {
            return '';
        }

        return substr($currentUrl, 0, $position + 1)
            . $skuFilename;
    }
}
