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
            'villas, hotels and resorts',
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

    public function testPrefersTitleTopicAndFiltersCompatibilityNoise(): void
    {
        $content = (new ProductEditorialDraftBuilder())->build(
            'Edubin – Education WordPress Theme',
            'pixelcurve',
            CatalogProductType::THEME,
            [
                'learndash',
                'lms',
                'loco translate',
                'rtl',
                'woocommerce',
                'wpml',
            ],
            '2026-08-28'
        );

        self::assertStringContainsString(
            'образование и онлайн-обучение и LMS',
            $content['ruShort']
        );
        self::assertStringContainsString(
            'education and online learning and LMS',
            $content['enShort']
        );

        $noiseTags = [
            'learndash',
            'loco translate',
            'rtl',
            'woocommerce',
            'wpml',
        ];

        foreach ($noiseTags as $noise) {
            self::assertStringNotContainsString(
                $noise,
                strtolower($content['enLong'])
            );
        }
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
