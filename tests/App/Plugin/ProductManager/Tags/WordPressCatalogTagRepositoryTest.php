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
