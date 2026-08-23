<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductUpdateResult
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public array $logs
    ) {
    }
}
