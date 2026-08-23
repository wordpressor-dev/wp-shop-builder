<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft\Contracts;

use WPShop\App\Plugin\ProductManager\Draft\ExistingProduct;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

interface ProductDraftGatewayInterface
{
    public function findBySlug(
        string $slug
    ): ?ExistingProduct;

    public function findBySku(
        string $sku
    ): ?ExistingProduct;

    public function createCore(
        ProductDraftData $data
    ): int;
}
