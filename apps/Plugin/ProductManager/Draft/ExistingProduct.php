<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

final readonly class ExistingProduct
{
    public function __construct(
        public int $id,
        public string $status
    ) {
    }
}
