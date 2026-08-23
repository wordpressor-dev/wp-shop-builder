<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Write;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;

final class ProductTaxonomyWriter implements
    ProductDraftWriterInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    /**
     * @return list<string>
     */
    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        $categoryId = $this->termId(
            'product_cat',
            'Темы',
            'themes',
            true
        );
        $brandId = $this->termId(
            'product_brand',
            'Themeforest',
            'themeforest',
            true
        );
        $categoryAttributeId = $this->termId(
            'pa_categori',
            'Темы',
            'themes',
            true
        );
        $companyAttributeId = $this->termId(
            'pa_company',
            'Themeforest',
            'themeforest',
            true
        );
        $developerSlug = (string) ($this->call)(
            'sanitize_title',
            $data->developer
        );
        $developerId = $this->termId(
            'pa_developer',
            $data->developer,
            $developerSlug,
            true
        );

        $this->setTerms(
            $productId,
            'product_cat',
            [$categoryId]
        );
        $this->setTerms(
            $productId,
            'product_brand',
            [$brandId]
        );
        $this->setTerms(
            $productId,
            'pa_categori',
            [$categoryAttributeId]
        );
        $this->setTerms(
            $productId,
            'pa_company',
            [$companyAttributeId]
        );
        $this->setTerms(
            $productId,
            'pa_developer',
            [$developerId]
        );

        [$productTagIds, $attributeTagIds] =
            $this->tagIds($data->tags);

        $this->setTerms(
            $productId,
            'product_tag',
            $productTagIds
        );
        $this->setTerms(
            $productId,
            'pa_tags',
            $attributeTagIds
        );

        ($this->call)(
            'update_post_meta',
            $productId,
            '_product_attributes',
            $this->attributes()
        );

        return [
            'product_cat = Темы',
            'product_brand = Themeforest',
            'pa_developer = ' . $data->developer,
            'TAGS ASSIGNED = ' . count($productTagIds),
            'TAG POLICY = EXISTING_ONLY; NEW_TAGS_CREATED = 0',
            'ATTRIBUTES = category/company/developer/tags',
        ];
    }

    /**
     * @param list<CatalogTag> $tags
     * @return array{0: list<int>, 1: list<int>}
     */
    private function tagIds(array $tags): array
    {
        $productTagIds = [];
        $attributeTagIds = [];

        foreach ($tags as $tag) {
            $productTagIds[] = $this->termId(
                'product_tag',
                $tag->name,
                $tag->slug,
                false
            );
            $attributeTagIds[] = $this->termId(
                'pa_tags',
                $tag->name,
                $tag->slug,
                false
            );
        }

        return [$productTagIds, $attributeTagIds];
    }

    private function termId(
        string $taxonomy,
        string $name,
        string $slug,
        bool $create
    ): int {
        $term = ($this->call)(
            'get_term_by',
            'slug',
            $slug,
            $taxonomy
        );

        $termId = $this->extractTermId($term);

        if ($termId > 0) {
            return $termId;
        }

        $term = ($this->call)(
            'get_term_by',
            'name',
            $name,
            $taxonomy
        );
        $termId = $this->extractTermId($term);

        if ($termId > 0) {
            return $termId;
        }

        if (! $create) {
            throw new RuntimeException(
                'Existing catalog tag disappeared: '
                . $taxonomy . '|' . $slug
            );
        }

        $created = ($this->call)(
            'wp_insert_term',
            $name,
            $taxonomy,
            ['slug' => $slug]
        );

        if (($this->call)('is_wp_error', $created)) {
            throw new RuntimeException(
                'Cannot create term: '
                . $taxonomy . '|' . $slug
            );
        }

        $termId = $this->extractTermId($created);

        if ($termId <= 0) {
            throw new RuntimeException(
                'Invalid term ID: '
                . $taxonomy . '|' . $slug
            );
        }

        return $termId;
    }

    private function extractTermId(mixed $term): int
    {
        if (is_array($term)) {
            return isset($term['term_id'])
                ? (int) $term['term_id']
                : 0;
        }

        if (is_object($term)) {
            $values = get_object_vars($term);

            return isset($values['term_id'])
                ? (int) $values['term_id']
                : 0;
        }

        return 0;
    }

    /**
     * @param list<int> $termIds
     */
    private function setTerms(
        int $productId,
        string $taxonomy,
        array $termIds
    ): void {
        ($this->call)(
            'wp_set_object_terms',
            $productId,
            $termIds,
            $taxonomy,
            false
        );
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function attributes(): array
    {
        $attributes = [];

        foreach (
            [
                'pa_categori',
                'pa_company',
                'pa_developer',
                'pa_tags',
            ] as $position => $taxonomy
        ) {
            $attributes[$taxonomy] = [
                'name' => $taxonomy,
                'value' => '',
                'position' => $position,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 1,
            ];
        }

        return $attributes;
    }
}
