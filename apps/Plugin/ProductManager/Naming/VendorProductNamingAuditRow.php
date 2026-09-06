<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

final readonly class VendorProductNamingAuditRow
{
    public function __construct(
        public int $productId,
        public string $currentTitle,
        public string $currentBaseTitle,
        public string $headerName,
        public string $recommendedTitle,
        public string $productType,
        public string $action,
        public string $confidence,
        public string $evidence,
        public string $reason
    ) {
    }
}
