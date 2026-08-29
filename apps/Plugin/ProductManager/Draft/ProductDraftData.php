<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;

final readonly class ProductDraftData
{
    public string $baseTitle;
    public string $slug;
    public int $itemId;
    public string $version;
    public string $sourceUpdateDate;
    public string $developer;
    public string $price;
    public string $salesPage;
    public string $skuFilename;
    public string $downloadUrl;
    public int $featuredImageId;

    /** @var list<CatalogTag> */
    public array $tags;

    public string $shortDescription;
    public string $longDescription;
    public string $metaDescription;
    public string $enShortDescription;
    public string $enLongDescription;
    public string $enMetaDescription;
    public string $notes;
    public bool $hit;
    public bool $new;

    /**
     * @param list<CatalogTag> $tags
     */
    public function __construct(
        string $baseTitle,
        string $slug,
        int $itemId,
        string $version,
        string $sourceUpdateDate,
        string $developer,
        string $price,
        string $salesPage,
        string $skuFilename,
        string $downloadUrl,
        int $featuredImageId,
        array $tags,
        string $shortDescription,
        string $longDescription,
        string $metaDescription,
        string $enShortDescription,
        string $enLongDescription,
        string $enMetaDescription,
        string $notes,
        bool $hit,
        bool $new
    ) {
        if ($this->isImportQueueDraft($notes)) {
            $editorial = (new ProductEditorialDraftBuilder())->build(
                $baseTitle,
                $developer,
                CatalogProductType::infer($baseTitle, $salesPage),
                $this->editorialSignals($baseTitle, $tags),
                $sourceUpdateDate
            );
            $shortDescription = $editorial['ruShort'];
            $longDescription = $editorial['ruLong'];
            $metaDescription = $editorial['ruMeta'];
            $enShortDescription = $editorial['enShort'];
            $enLongDescription = $editorial['enLong'];
            $enMetaDescription = $editorial['enMeta'];
        }

        $this->baseTitle = $baseTitle;
        $this->slug = $slug;
        $this->itemId = $itemId;
        $this->version = $version;
        $this->sourceUpdateDate = $sourceUpdateDate;
        $this->developer = $developer;
        $this->price = $price;
        $this->salesPage = $salesPage;
        $this->skuFilename = $skuFilename;
        $this->downloadUrl = $downloadUrl;
        $this->featuredImageId = $featuredImageId;
        $this->tags = $tags;
        $this->shortDescription = $shortDescription;
        $this->longDescription = $longDescription;
        $this->metaDescription = $metaDescription;
        $this->enShortDescription = $enShortDescription;
        $this->enLongDescription = $enLongDescription;
        $this->enMetaDescription = $enMetaDescription;
        $this->notes = $notes;
        $this->hit = $hit;
        $this->new = $new;
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
            $this->baseTitle,
            $this->slug,
            $this->itemId,
            $this->version,
            $this->sourceUpdateDate,
            $this->developer,
            $this->price,
            $this->salesPage,
            $skuFilename,
            $downloadUrl,
            $this->featuredImageId,
            $this->tags,
            $this->shortDescription,
            $this->longDescription,
            $this->metaDescription,
            $this->enShortDescription,
            $this->enLongDescription,
            $this->enMetaDescription,
            $this->notes,
            $this->hit,
            $this->new
        );
    }

    private function isImportQueueDraft(string $notes): bool
    {
        return str_starts_with(
            trim($notes),
            'Created from WP Shop Builder Import Queue.'
        );
    }

    /**
     * @param list<CatalogTag> $tags
     * @return list<string>
     */
    private function editorialSignals(string $title, array $tags): array
    {
        $signals = [];

        foreach ($tags as $tag) {
            if (trim($tag->name) !== '') {
                $signals[] = trim($tag->name);
            }
        }

        $titleParts = preg_split('/[^a-z0-9-]+/i', $title) ?: [];
        $titleParts = array_values(array_filter(
            $titleParts,
            static fn (string $value): bool => trim($value) !== ''
        ));

        foreach (array_slice($titleParts, 1) as $part) {
            $signals[] = $part;
        }

        if (str_contains(strtolower($title), 'real estate')) {
            $signals[] = 'real estate';
        }

        return array_values(array_unique($signals));
    }
}
