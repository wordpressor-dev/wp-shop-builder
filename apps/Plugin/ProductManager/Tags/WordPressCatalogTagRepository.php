<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

use Closure;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;

final class WordPressCatalogTagRepository implements
    CatalogTagRepositoryInterface
{
    /** @var Closure(string, string, string): bool */
    private readonly Closure $lookup;

    /**
     * @param null|Closure(string, string, string): bool $lookup
     */
    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? static function (
            string $taxonomy,
            string $name,
            string $slug
        ): bool {
            if (
                ! function_exists('taxonomy_exists')
                || ! function_exists('get_term_by')
            ) {
                return false;
            }

            /** @var callable-string $taxonomyExists */
            $taxonomyExists = 'taxonomy_exists';

            /** @var callable-string $getTermBy */
            $getTermBy = 'get_term_by';

            if (! $taxonomyExists($taxonomy)) {
                return false;
            }

            $term = $getTermBy(
                'slug',
                $slug,
                $taxonomy
            );

            if ($term === false && $name !== '') {
                $term = $getTermBy(
                    'name',
                    $name,
                    $taxonomy
                );
            }

            return is_object($term);
        };
    }

    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return ($this->lookup)(
            'product_tag',
            $name,
            $slug
        ) && ($this->lookup)(
            'pa_tags',
            $name,
            $slug
        );
    }
}
