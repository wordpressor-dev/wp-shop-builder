<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

final readonly class VendorCoverAuditRow
{
    public function __construct(
        public int $productId,
        public string $currentTitle,
        public string $salesPage,
        public string $sourceType,
        public bool $marketplaceProtected,
        public int $featuredImageId,
        public string $imageUrl,
        public string $imageFilename,
        public int $imageWidth,
        public int $imageHeight,
        public string $imageFormat,
        public string $imageStatus,
        public bool $generated,
        public bool $locked,
        public bool $standardMatch,
        public string $action,
        public string $reason
    ) {
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'currentTitle' => $this->currentTitle,
            'salesPage' => $this->salesPage,
            'sourceType' => $this->sourceType,
            'marketplaceProtected' => $this->marketplaceProtected,
            'featuredImageId' => $this->featuredImageId,
            'imageUrl' => $this->imageUrl,
            'imageFilename' => $this->imageFilename,
            'imageWidth' => $this->imageWidth,
            'imageHeight' => $this->imageHeight,
            'imageFormat' => $this->imageFormat,
            'imageStatus' => $this->imageStatus,
            'generated' => $this->generated,
            'locked' => $this->locked,
            'standardMatch' => $this->standardMatch,
            'action' => $this->action,
            'reason' => $this->reason,
        ];
    }
}
