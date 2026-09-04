<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\EnvatoOfficialFactsExtractor;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class EnvatoOfficialFactsExtractorTest extends TestCase
{
    public function testFiltersCompatibilityNoiseForCommerceEditorialContent(): void
    {
        $extractor = new EnvatoOfficialFactsExtractor();
        $official = $extractor->extract([
            'tags' => [
                'woocommerce',
                'elementor',
                'wpml',
                'rtl',
                'responsive',
                'gutenberg',
                'contact form 7',
            ],
        ]);

        self::assertContains('ecommerce', $official['signals']);
        self::assertContains('wpml', $official['signals']);
        self::assertContains('rtl', $official['signals']);
        self::assertNotContains('woocommerce', $official['signals']);
        self::assertNotContains('elementor', $official['signals']);
        self::assertNotContains('responsive', $official['signals']);
        self::assertNotContains('gutenberg', $official['signals']);
        self::assertNotContains('contact form 7', $official['signals']);
        self::assertNotContains(
            'совместимость с WooCommerce',
            $official['ruFacts']
        );
        self::assertNotContains(
            'поддержка Elementor для визуальной настройки страниц',
            $official['ruFacts']
        );
        self::assertSame(['поддержка RTL-языков'], $official['ruFacts']);

        $summaryRu = 'Elessi — современная WooCommerce тема с AJAX-фильтрами '
            . 'и поддержкой RTL. Идеальна для создания быстрых интернет-магазинов.';
        $summaryEn = 'Elessi is a modern WooCommerce theme with AJAX filters and '
            . 'RTL support. Ideal for creating fast online stores.';
        $content = (new ProductEditorialDraftBuilder())->build(
            'Elessi - WooCommerce AJAX WordPress Theme - RTL support',
            'NasaTheme',
            CatalogProductType::THEME,
            $official['signals'],
            '2026-08-30',
            [
                'ruShort' => $summaryRu,
                'ruLong' => $summaryRu,
                'enShort' => $summaryEn,
                'enLong' => $summaryEn,
            ]
        );

        self::assertStringContainsString('интернет-магазины', $content['ruLong']);
        self::assertStringContainsString('Многоязычные проекты', $content['ruLong']);
        self::assertStringNotContainsString(
            'WooCommerce и коммерческие сценарии',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            'Elementor и настройка страниц',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            'курсов, цифровых материалов',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            'courses, digital content',
            $content['enLong']
        );
    }
}
