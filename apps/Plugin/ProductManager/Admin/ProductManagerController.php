<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Admin;

use InvalidArgumentException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Translation\ProductTranslationResult;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;

final class ProductManagerController
{
    public function __construct(
        private readonly EnvatoClientInterface $envato,
        private readonly ExistingTagSelector $tags,
        private readonly ?ProductDraftCreator $draftCreator = null,
        private readonly ?TranslatePressProductTranslator $translator = null,
        private readonly ?ExistingCatalogTagParser $tagParser = null
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
                'ENVATO VERSION = ' . (
                    $item->version !== ''
                        ? $item->version
                        : 'REVIEW_REQUIRED'
                ),
                'VERSION CHECK = MANUAL REQUIRED BEFORE DRAFT',
                'MANUAL VERSION = SOURCE OF TRUTH AT DRAFT CREATE',
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

    /**
     * @return list<CatalogTag>
     */
    public function parseExistingTags(string $value): array
    {
        if ($this->tagParser === null) {
            if (trim($value) === '') {
                return [];
            }

            throw new InvalidArgumentException(
                'Catalog tag parser is unavailable.'
            );
        }

        return $this->tagParser->parse($value);
    }

    public function preflightDraft(
        ProductDraftData $data
    ): ProductDraftResult {
        if ($this->draftCreator === null) {
            return new ProductDraftResult(
                false,
                null,
                ['DRAFT_CREATOR_UNAVAILABLE']
            );
        }

        $prepared = $this->prepareIdentity(
            $data,
            'PREFLIGHT REQUEST = RECEIVED'
        );

        if ($prepared instanceof ProductDraftResult) {
            return $prepared;
        }

        [$preparedData, $identityLogs] = $prepared;
        $result = $this->draftCreator->preflight($preparedData);

        return new ProductDraftResult(
            $result->success,
            null,
            array_merge($identityLogs, $result->logs)
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

        $prepared = $this->prepareIdentity(
            $data,
            'CREATE REQUEST = RECEIVED'
        );

        if ($prepared instanceof ProductDraftResult) {
            return $prepared;
        }

        [$preparedData, $identityLogs] = $prepared;
        $result = $this->draftCreator->create($preparedData);

        return new ProductDraftResult(
            $result->success,
            $result->productId,
            array_merge($identityLogs, $result->logs)
        );
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
     * @return array{ProductDraftData, list<string>}|ProductDraftResult
     */
    private function prepareIdentity(
        ProductDraftData $data,
        string $requestLog
    ): array|ProductDraftResult {
        try {
            $canonicalSku = ProductSkuFilename::synchronize(
                $data->skuFilename,
                $data->itemId,
                $data->salesPage,
                $data->version
            );
        } catch (InvalidArgumentException $exception) {
            return new ProductDraftResult(
                false,
                null,
                [
                    $requestLog,
                    'STOP: DRAFT NOT CREATED.',
                    'VERSION / SKU SAFETY CHECK = FAILED',
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        $logs = [
            $requestLog,
            'MANUAL VERSION = SOURCE OF TRUTH: ' . $data->version,
        ];

        if ($canonicalSku !== $data->skuFilename) {
            $logs[] = 'SKU AUTO-SYNC: '
                . ($data->skuFilename !== ''
                    ? $data->skuFilename
                    : '[empty]')
                . ' -> '
                . $canonicalSku;
        } else {
            $logs[] = 'SKU / VERSION = MATCH';
        }

        return [
            $data->withSkuFilename($canonicalSku),
            $logs,
        ];
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
