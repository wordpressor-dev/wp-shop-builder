<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

final readonly class ProductArchiveIdentityResult
{
    public function __construct(
        public bool $success,
        public string $productType,
        public string $name,
        public string $version,
        public string $source
    ) {
    }
}
