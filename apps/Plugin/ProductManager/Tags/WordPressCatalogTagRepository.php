<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

use Closure;
use RuntimeException;
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
            $taxonomyExists = self::wordpressCallable(
                'taxonomy_exists'
            );
            $getTermBy = self::wordpressCallable(
                'get_term_by'
            );

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

    private static function wordpressCallable(
        string $name
    ): Closure {
        if (! is_callable($name)) {
            throw new RuntimeException(
                'WordPress taxonomy API is unavailable: ' . $name
            );
        }

        return Closure::fromCallable($name);
    }
}
