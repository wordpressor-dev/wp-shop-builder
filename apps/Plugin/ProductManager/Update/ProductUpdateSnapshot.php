<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductUpdateSnapshot
{
    public function __construct(
        public int $productId,
        public string $status,
        public string $title,
        public string $baseTitle,
        public int $itemId,
        public string $version,
        public string $sourceUpdateDate,
        public string $salesPage,
        public string $skuFilename,
        public string $downloadUrl
    ) {
    }
}
