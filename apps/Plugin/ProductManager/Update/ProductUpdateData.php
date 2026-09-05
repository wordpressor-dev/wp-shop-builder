<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductUpdateData
{
    public function __construct(
        public int $productId,
        public string $baseTitle,
        public int $itemId,
        public string $currentVersion,
        public string $version,
        public string $sourceUpdateDate,
        public string $salesPage,
        public string $currentSku,
        public string $skuFilename,
        public string $downloadUrl,
        public string $sourceType = 'envato'
    ) {
    }

    public function title(): string
    {
        return trim($this->baseTitle . ' ' . $this->version);
    }

    public function withVersion(string $version): self
    {
        return new self(
            $this->productId,
            $this->baseTitle,
            $this->itemId,
            $this->currentVersion,
            trim($version),
            $this->sourceUpdateDate,
            $this->salesPage,
            $this->currentSku,
            $this->skuFilename,
            $this->downloadUrl,
            $this->sourceType
        );
    }

    public function withSkuFilename(string $skuFilename): self
    {
        return $this->withArchive(
            $skuFilename,
            $this->downloadUrl
        );
    }

    public function withArchive(
        string $skuFilename,
        string $downloadUrl
    ): self {
        return new self(
            $this->productId,
            $this->baseTitle,
            $this->itemId,
            $this->currentVersion,
            $this->version,
            $this->sourceUpdateDate,
            $this->salesPage,
            $this->currentSku,
            $skuFilename,
            $downloadUrl,
            $this->sourceType
        );
    }
}
