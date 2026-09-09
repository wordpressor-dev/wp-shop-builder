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
            'отели, курорты и виллы',
            $content['ruShort']
        );
        self::assertStringContainsString(
            'hotels, resorts and villas',
            $content['enShort']
        );
        foreach (
            [
                '<h3>Основные возможности</h3>',
                '<h3>Кому подходит</h3>',
                '<h3>Совместимость и требования</h3>',
                '<h3>Что важно знать</h3>',
            ] as $heading
        ) {
            self::assertStringContainsString(
                $heading,
                $content['ruLong']
            );
        }
        foreach (
            [
                '<h3>Key features</h3>',
                '<h3>Who it is for</h3>',
                '<h3>Compatibility and requirements</h3>',
                '<h3>What to know</h3>',
            ] as $heading
        ) {
            self::assertStringContainsString(
                $heading,
                $content['enLong']
            );
        }
        self::assertStringNotContainsString(
            '<h3>Elementor и настройка страниц</h3>',
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
            'образование, онлайн-обучение и LMS',
            $content['ruShort']
        );
        self::assertStringContainsString(
            'education, online learning and LMS',
            $content['enShort']
        );
        self::assertStringContainsString(
            '<h3>Совместимость и требования</h3>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'WooCommerce',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'WPML',
            $content['ruLong']
        );
    }

    public function testPreservesLegacyFactsInsideRichV28Structure(): void
    {
        $legacyRuShort = '<strong>Edubin – Education WordPress Theme</strong>'
            . ' — это современная тема WordPress для школ, курсов,'
            . ' университетов и онлайн-образования.';
        $legacyRuLong = '<p>Адаптивный дизайн, интеграция с LMS,'
            . ' расписание занятий, страницы преподавателей, календарь событий,'
            . ' галерея, отзывы и форма обратной связи.</p>';
        $legacyEnShort = '<p>Edubin is a WordPress education theme for schools,'
            . ' courses and universities.</p>';
        $legacyEnLong = '<p>Includes class schedules, instructor pages, events,'
            . ' galleries, testimonials and contact forms.</p>';
        $content = (new ProductEditorialDraftBuilder())->build(
            'Edubin – Education WordPress Theme',
            'pixelcurve',
            CatalogProductType::THEME,
            [
                'education',
                'lms',
                'elementor',
                'woocommerce',
                'wpml',
            ],
            '2026-08-28',
            [
                'ruShort' => $legacyRuShort,
                'ruLong' => $legacyRuLong,
                'enShort' => $legacyEnShort,
                'enLong' => $legacyEnLong,
            ]
        );

        self::assertStringContainsString(
            'школ, курсов, университетов',
            $content['ruShort']
        );
        self::assertStringContainsString(
            '<h3>Основные возможности</h3>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'расписание занятий',
            $content['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Кому подходит</h3>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Совместимость и требования</h3>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Что важно знать</h3>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'WooCommerce',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'WPML',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            'Техническая информация',
            $content['ruLong']
        );
        self::assertStringContainsString(
            'class schedules',
            $content['enLong']
        );
        self::assertStringContainsString(
            '<h3>Who it is for</h3>',
            $content['enLong']
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
