<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductUpdateSuggestion
{
    public function __construct(
        public string $version,
        public string $updateDate,
        public string $skuFilename,
        public string $downloadUrl
    ) {
    }
}
