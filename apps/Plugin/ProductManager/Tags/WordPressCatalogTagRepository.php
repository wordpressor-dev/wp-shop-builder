<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CanonicalCatalogTagRepositoryInterface;

final class WordPressCatalogTagRepository implements
    CanonicalCatalogTagRepositoryInterface
{
    /** @var Closure(string, string, string): mixed */
    private readonly Closure $lookup;

    /**
     * @param null|Closure(string, string, string): mixed $lookup
     */
    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? static function (
            string $taxonomy,
            string $name,
            string $slug
        ): mixed {
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

            return $term;
        };
    }

    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return $this->termExists(
            ($this->lookup)(
                'product_tag',
                $name,
                $slug
            )
        ) && $this->termExists(
            ($this->lookup)(
                'pa_tags',
                $name,
                $slug
            )
        );
    }

    public function resolveInBoth(
        string $name,
        string $slug
    ): ?CatalogTag {
        $productTag = ($this->lookup)(
            'product_tag',
            $name,
            $slug
        );
        $attributeTag = ($this->lookup)(
            'pa_tags',
            $name,
            $slug
        );

        if (
            ! $this->termExists($productTag)
            || ! $this->termExists($attributeTag)
        ) {
            return null;
        }

        $resolved = $this->catalogTag(
            $productTag,
            $name,
            $slug
        );

        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = $this->catalogTag(
            $attributeTag,
            $name,
            $slug
        );

        return $resolved ?? new CatalogTag(
            $name,
            $slug
        );
    }

    private function termExists(mixed $term): bool
    {
        if (is_bool($term)) {
            return $term;
        }

        return is_object($term) || is_array($term);
    }

    private function catalogTag(
        mixed $term,
        string $fallbackName,
        string $fallbackSlug
    ): ?CatalogTag {
        if (is_object($term)) {
            $term = get_object_vars($term);
        }

        if (! is_array($term)) {
            return null;
        }

        $name = isset($term['name']) && is_scalar($term['name'])
            ? trim((string) $term['name'])
            : '';
        $slug = isset($term['slug']) && is_scalar($term['slug'])
            ? trim((string) $term['slug'])
            : '';

        if ($name === '') {
            $name = $fallbackName;
        }

        if ($slug === '') {
            $slug = $fallbackSlug;
        }

        if ($name === '' || $slug === '') {
            return null;
        }

        return new CatalogTag(
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
