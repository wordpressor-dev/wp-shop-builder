<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Tags;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;

final class WordPressCatalogTagRepositoryTest extends TestCase
{
    public function testRequiresTagToExistInBothTaxonomies(): void
    {
        $calls = [];

        $repository = new WordPressCatalogTagRepository(
            static function (
                string $taxonomy,
                string $name,
                string $slug
            ) use (&$calls): bool {
                $calls[] = [$taxonomy, $name, $slug];

                return in_array(
                    $taxonomy,
                    ['product_tag', 'pa_tags'],
                    true
                ) && $slug === 'marketplace';
            }
        );

        self::assertTrue(
            $repository->existsInBoth(
                'торговая площадка',
                'marketplace'
            )
        );
        self::assertCount(2, $calls);
    }

    public function testResolvesCanonicalWordPressTermNameAndSlug(): void
    {
        $repository = new WordPressCatalogTagRepository(
            static function (
                string $taxonomy,
                string $name,
                string $slug
            ): object|false {
                if (
                    ! in_array(
                        $taxonomy,
                        ['product_tag', 'pa_tags'],
                        true
                    )
                    || $slug !== 'finance-law'
                ) {
                    return false;
                }

                return (object) [
                    'term_id' => $taxonomy === 'product_tag' ? 10 : 11,
                    'name' => 'финансы и право',
                    'slug' => 'finance-law',
                ];
            }
        );

        $tag = $repository->resolveInBoth(
            'finance',
            'finance-law'
        );

        self::assertNotNull($tag);
        self::assertSame('финансы и право', $tag->name);
        self::assertSame('finance-law', $tag->slug);
    }

    public function testCanonicalLookupAcceptsWordPressTermObjects(): void
    {
        $repository = new WordPressCatalogTagRepository(
            static function (
                string $taxonomy,
                string $name,
                string $slug
            ): mixed {
                if (
                    ! in_array(
                        $taxonomy,
                        ['product_tag', 'pa_tags'],
                        true
                    )
                ) {
                    return false;
                }

                return (object) [
                    'term_id' => $taxonomy === 'product_tag' ? 21 : 22,
                    'name' => 'агентство',
                    'slug' => 'agency',
                ];
            }
        );

        $tag = $repository->resolveInBoth(
            'agency',
            'agency'
        );

        self::assertNotNull($tag);
        self::assertSame('агентство', $tag->name);
        self::assertSame('agency', $tag->slug);
    }

    public function testRejectsTagMissingFromAttributeTaxonomy(): void
    {
        $repository = new WordPressCatalogTagRepository(
            static fn(string $taxonomy): bool =>
                $taxonomy === 'product_tag'
        );

        self::assertFalse(
            $repository->existsInBoth(
                'elementor',
                'elementor'
            )
        );
    }
}
