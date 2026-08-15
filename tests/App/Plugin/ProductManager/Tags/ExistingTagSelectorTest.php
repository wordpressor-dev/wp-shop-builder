<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Tags;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;

final class ExistingTagSelectorTest extends TestCase
{
    public function testSelectsOnlyMappedTagsThatAlreadyExistInBothTaxonomies(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'marketplace',
            'multi-vendor',
            'digital-product',
            'shop',
            'music-bands',
        ]);

        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'name' => 'Aabbe - Digital Marketplace WordPress Theme',
            'tags' => [
                'audio marketplace',
                'commissions',
                'coupon',
                'digital marketplace',
                'digital shop',
                'easy digital downloads',
                'frontend submissions',
                'multi vendors',
                'responsive',
                'selling',
            ],
        ]);

        self::assertSame(
            [
                'multi-vendor|multi-vendor',
                'торговая площадка|marketplace',
                'цифровые товары|digital-product',
                'интернет-магазин|shop',
                'музыка и группы|music-bands',
            ],
            array_map(
                static fn($tag): string => $tag->line(),
                $tags
            )
        );
    }

    public function testDoesNotSelectGutenbergWhenPayloadSaysNo(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'gutenberg',
        ]);

        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'attributes' => [
                'Gutenberg Optimized' => 'No',
            ],
        ]);

        self::assertSame([], $tags);
    }
}

final class ExistingTagSelectorRepository implements
    CatalogTagRepositoryInterface
{
    /**
     * @param list<string> $existingSlugs
     */
    public function __construct(
        private readonly array $existingSlugs
    ) {
    }

    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return in_array(
            $slug,
            $this->existingSlugs,
            true
        );
    }
}
