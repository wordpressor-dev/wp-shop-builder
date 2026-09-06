<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

final readonly class VendorProductNamingReviewV5Row
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
        public string $packageStatus,
        public string $translationState,
        public string $englishTitle,
        public string $translationSafety,
        public string $recommendedTitle,
        public string $namingAction,
        public string $confidence,
        public string $reason
    ) {
    }
}
