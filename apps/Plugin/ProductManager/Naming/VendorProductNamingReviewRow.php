<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

final readonly class VendorProductNamingReviewRow
{
    public function __construct(
        public int $productId,
        public string $currentTitle,
        public string $salesPage,
        public string $salesPageStatus,
        public string $salesPageH1,
        public string $salesPageTitle,
        public string $zipHeaderName,
        public string $productType,
        public string $translationState,
        public string $englishTitle,
        public string $recommendedTitle,
        public string $action,
        public string $confidence,
        public string $reason
    ) {
    }
}
