<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

final readonly class ProductDraftResult
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public ?int $productId,
        public array $logs
    ) {
    }
}
