<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;

final readonly class ProductDraftData
{
    /**
     * @param list<CatalogTag> $tags
     */
    public function __construct(
        public string $baseTitle,
        public string $slug,
        public int $itemId,
        public string $version,
        public string $sourceUpdateDate,
        public string $developer,
        public string $price,
        public string $salesPage,
        public string $skuFilename,
        public string $downloadUrl,
        public int $featuredImageId,
        public array $tags,
        public string $shortDescription,
        public string $longDescription,
        public string $metaDescription,
        public string $enShortDescription,
        public string $enLongDescription,
        public string $enMetaDescription,
        public string $notes,
        public bool $hit,
        public bool $new
    ) {
    }

    public function title(): string
    {
        return trim(
            $this->baseTitle . ' ' . $this->version
        );
    }

    public function hasCompleteEnglishContent(): bool
    {
        return $this->enShortDescription !== ''
            && $this->enLongDescription !== ''
            && $this->enMetaDescription !== '';
    }
}
