<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductEditorialBulkSafetyTest extends TestCase
{
    public function testWooCommerceSectionDoesNotInventCourseSales(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $summary = 'Yoast WooCommerce SEO — специализированный плагин для '
            . 'оптимизации интернет-магазина и товарных страниц.';

        $content = $builder->build(
            'Yoast WooCommerce SEO',
            'Yoast',
            CatalogProductType::PLUGIN,
            ['woocommerce', 'ecommerce'],
            '',
            [
                'ruShort' => $summary,
                'ruLong' => $summary,
            ]
        );

        self::assertStringContainsString(
            'интернет-магазинах и других e-commerce проектах',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            'курсов, цифровых материалов',
            $content['ruLong']
        );
    }

    public function testCommaClausesAreNotTurnedIntoFakeFeatures(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $summary = 'Yoast Video SEO — плагин для оптимизации видео. '
            . 'Создает XML-карту сайта для видео, добавляет структурированные '
            . 'данные и метатеги, что повышает шансы на попадание в Google Videos.';

        $content = $builder->build(
            'Yoast Video SEO',
            'Yoast',
            CatalogProductType::PLUGIN,
            [],
            '',
            [
                'ruShort' => $summary,
                'ruLong' => $summary,
            ]
        );

        self::assertStringContainsString(
            '<li>Создает XML-карту сайта для видео.</li>',
            $content['ruLong']
        );
        self::assertStringContainsString(
            '<li>добавляет структурированные данные и метатеги.</li>',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            '<li>что повышает шансы',
            $content['ruLong']
        );
    }

    public function testModifierFragmentsDoNotCreateOneItemFeatureLists(): void
    {
        $builder = new ProductEditorialDraftBuilder();
        $summary = 'TranslatePress Business — плагин для многоязычных сайтов. '
            . 'Позволяет переводить весь контент, включая динамический, '
            . 'с визуальным редактором.';

        $content = $builder->build(
            'TranslatePress Business',
            'Cozmoslabs',
            CatalogProductType::PLUGIN,
            [],
            '',
            [
                'ruShort' => $summary,
                'ruLong' => $summary,
            ]
        );

        self::assertStringNotContainsString(
            '<h3>Основные возможности TranslatePress Business</h3>',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            '<li>включая динамический.</li>',
            $content['ruLong']
        );
        self::assertStringNotContainsString(
            '<li>с визуальным редактором.</li>',
            $content['ruLong']
        );
    }
}
