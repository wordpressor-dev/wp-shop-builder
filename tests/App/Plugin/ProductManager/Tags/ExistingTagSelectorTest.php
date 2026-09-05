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

    public function testSelectsSoftwareOnlyForSoftwareThemeSignals(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'software',
        ]);
        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'name' => 'Softwerk - Software & SaaS Company WordPress Theme',
            'url' => 'https://themeforest.net/item/softwerk-theme/123456',
            'wordpress_theme_metadata' => [
                'version' => '1.0.0',
            ],
            'tags' => [
                'business',
                'software company',
                'saas',
            ],
        ]);

        self::assertSame(
            ['программное обеспечение|software'],
            array_map(
                static fn($tag): string => $tag->line(),
                $tags
            )
        );
    }

    public function testSelectsSoftwareForThemeWithSoftwareDemoTag(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'software',
        ]);
        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'name' => 'Multipurpose Business WordPress Theme',
            'url' => 'https://themeforest.net/item/business-theme/123456',
            'tags' => [
                'agency',
                'software',
                'portfolio',
            ],
        ]);

        self::assertSame(
            ['программное обеспечение|software'],
            array_map(
                static fn($tag): string => $tag->line(),
                $tags
            )
        );
    }

    public function testDoesNotSelectSoftwareForPluginJustBecauseItIsSoftware(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'software',
        ]);
        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'name' => 'Example WordPress Software Plugin',
            'url' => 'https://codecanyon.net/item/example-plugin/654321',
            'wordpress_plugin_metadata' => [
                'version' => '2.0.0',
            ],
            'tags' => [
                'software',
                'wordpress plugin',
            ],
        ]);

        self::assertSame([], $tags);
    }

    public function testDoesNotSelectSoftwareFromGenericDescriptionText(): void
    {
        $repository = new ExistingTagSelectorRepository([
            'software',
        ]);
        $selector = new ExistingTagSelector($repository);

        $tags = $selector->select([
            'name' => 'Restaurant WordPress Theme',
            'url' => 'https://themeforest.net/item/restaurant-theme/123456',
            'tags' => [
                'restaurant',
                'food',
                'booking',
            ],
            'description' => 'The package includes software documentation.',
        ]);

        self::assertSame([], $tags);
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
