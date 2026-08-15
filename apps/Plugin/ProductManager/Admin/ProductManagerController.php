<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Admin;

use Throwable;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Translation\ProductTranslationResult;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;

final class ProductManagerController
{
    public function __construct(
        private readonly EnvatoClientInterface $envato,
        private readonly ExistingTagSelector $tags,
        private readonly ?ProductDraftCreator $draftCreator = null,
        private readonly ?TranslatePressProductTranslator $translator = null
    ) {
    }

    public function autofill(
        string $itemUrl,
        string $token
    ): ProductManagerAutofillResult {
        try {
            $item = $this->envato->fetch(
                trim($itemUrl),
                trim($token)
            );
        } catch (Throwable $exception) {
            return new ProductManagerAutofillResult(
                false,
                [],
                [
                    'ENVATO AUTOFILL FAILED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        $selectedTags = $this->tags->select($item->source);

        $fields = [
            'base_title' => $item->baseTitle,
            'slug' => $item->productSlug,
            'item_id' => (string) $item->itemId,
            'version' => $item->version,
            'source_update_date' => $item->updatedDate,
            'developer' => $item->developer,
            'price' => '249',
            'sales_page' => $item->salesPage,
            'sku_filename' => $item->skuFilename,
            'tags' => $this->tagLines($selectedTags),
        ];

        return new ProductManagerAutofillResult(
            true,
            $fields,
            [
                'ENVATO AUTOFILL = READY',
                'ITEM ID = ' . $item->itemId,
                'VERSION = ' . (
                    $item->version !== ''
                        ? $item->version
                        : 'REVIEW_REQUIRED'
                ),
                'DEVELOPER = ' . (
                    $item->developer !== ''
                        ? $item->developer
                        : 'REVIEW_REQUIRED'
                ),
                'EXISTING TAGS SUGGESTED = '
                    . count($selectedTags),
                'EDITORIAL CONTENT = MANUAL',
            ]
        );
    }

    public function createDraft(
        ProductDraftData $data
    ): ProductDraftResult {
        if ($this->draftCreator === null) {
            return new ProductDraftResult(
                false,
                null,
                ['DRAFT_CREATOR_UNAVAILABLE']
            );
        }

        return $this->draftCreator->create($data);
    }

    public function translate(
        int $productId,
        string $enShort,
        string $enLong,
        string $enMeta
    ): ProductTranslationResult {
        if ($this->translator === null) {
            return new ProductTranslationResult(
                false,
                ['TRANSLATOR_UNAVAILABLE']
            );
        }

        return $this->translator->translate(
            $productId,
            $enShort,
            $enLong,
            $enMeta
        );
    }

    /**
     * @param list<CatalogTag> $tags
     */
    private function tagLines(array $tags): string
    {
        $lines = [];

        foreach ($tags as $tag) {
            $lines[] = $tag->name . '|' . $tag->slug;
        }

        return implode("\n", $lines);
    }
}
