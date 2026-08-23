<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft\Contracts;

use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

interface ProductDraftWriterInterface
{
    /**
     * @return list<string>
     */
    public function write(
        int $productId,
        ProductDraftData $data
    ): array;
}
