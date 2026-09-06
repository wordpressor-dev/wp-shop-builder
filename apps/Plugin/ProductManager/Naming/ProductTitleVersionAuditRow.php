<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

final readonly class ProductTitleVersionAuditRow
{
    public function __construct(
        public int $productId,
        public string $currentTitle,
        public string $storedVersion,
        public string $recommendedTitle,
        public string $source,
        public string $action,
        public string $reason
    ) {
    }
}
