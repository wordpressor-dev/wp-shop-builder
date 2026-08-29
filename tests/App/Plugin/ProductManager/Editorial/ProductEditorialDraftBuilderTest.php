<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductEditorialDraftBuilderTest extends TestCase
{
    public function testBuildsTopicAwareTemplateKitContent(): void
    {
        $content = (new ProductEditorialDraftBuilder())->build(
            'Villora – Villa & Hotel Resort Elementor Template Kit',
            'kitpixel',
            CatalogProductType::TEMPLATE_KIT,
            ['villa', 'hotel', 'resort', 'elementor'],
            '2026-07-15'
        );

        self::assertStringContainsString(
            'виллы, отели и курорты',
            $content['ruShort']
        );
        self::assertStringContainsString(
            'villa, hotel and resort',
            $content['enShort']
        );
        self::assertStringContainsString(
            '<strong>Разработчик:</strong> kitpixel.',
            $content['ruLong']
        );
        self::assertStringContainsString(
            '<strong>Source update date:</strong> 2026-07-15.',
            $content['enLong']
        );
        self::assertStringNotContainsString(
            'Перед публикацией проверьте описание',
            $content['ruLong']
        );
    }

    public function testBuildsCompleteThemeAndPluginDraftsWithoutTopics(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $theme = $builder->build(
            'Example – WordPress Theme',
            'Vendor',
            CatalogProductType::THEME
        );
        $plugin = $builder->build(
            'Example – WordPress Plugin',
            'Vendor',
            CatalogProductType::PLUGIN
        );

        foreach ([$theme, $plugin] as $content) {
            self::assertNotSame('', $content['ruShort']);
            self::assertNotSame('', $content['ruLong']);
            self::assertNotSame('', $content['ruMeta']);
            self::assertNotSame('', $content['enShort']);
            self::assertNotSame('', $content['enLong']);
            self::assertNotSame('', $content['enMeta']);
        }
    }
}
