<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Write;

use Closure;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class SureRankWriter implements ProductDraftWriterInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        $settings = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );

        if (! is_array($settings)) {
            $settings = [];
        }

        $description = $data->metaDescription;
        $autoDraft = str_contains(
            $data->notes,
            'Created from WP Shop Builder Import Queue.'
        );

        if ($autoDraft) {
            $productType = CatalogProductType::infer(
                $data->baseTitle,
                $data->salesPage
            );
            $tagNames = [];

            foreach ($data->tags as $tag) {
                $tagNames[] = $tag->name;
            }

            $editorial = (new ProductEditorialDraftBuilder())->build(
                $data->baseTitle,
                $data->developer,
                $productType,
                $tagNames,
                $data->sourceUpdateDate
            );
            $description = $editorial['ruMeta'];
        }

        $settings['page_description'] = $description;

        ($this->call)(
            'update_post_meta',
            $productId,
            'surerank_settings_general',
            $settings
        );

        return [
            $autoDraft
                ? 'SURERANK META DESCRIPTION = AUTO-DRAFT UPDATED'
                : 'SURERANK META DESCRIPTION = UPDATED',
        ];
    }
}
