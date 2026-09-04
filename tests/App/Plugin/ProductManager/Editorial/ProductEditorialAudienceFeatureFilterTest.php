<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductEditorialAudienceFeatureFilterTest extends TestCase
{
    public function testAudienceSentenceIsNotConvertedIntoFeatureBullets(): void
    {
        $ru = 'Sober – WooCommerce WordPress Theme — элегантная WooCommerce тема '
            . 'для создания стильных интернет-магазинов. Идеально подходит для '
            . 'модных брендов, магазинов электроники и креативных проектов с '
            . 'современным дизайном и удобным управлением.';
        $en = 'Sober – WooCommerce WordPress Theme — an elegant WooCommerce theme '
            . 'for creating stylish online stores. Ideal for fashion brands, '
            . 'electronics stores, and creative projects with modern design and '
            . 'easy management.';

        $draft = (new ProductEditorialDraftBuilder())->build(
            'Sober – WooCommerce WordPress Theme',
            'uixthemes',
            CatalogProductType::THEME,
            [],
            '',
            [
                'ruShort' => $ru,
                'ruLong' => $ru,
                'enShort' => $en,
                'enLong' => $en,
            ]
        );

        self::assertStringNotContainsString(
            '<h3>Основные возможности Sober</h3>',
            $draft['ruLong']
        );
        self::assertStringNotContainsString(
            '<li>Идеально подходит для модных брендов.</li>',
            $draft['ruLong']
        );
        self::assertStringNotContainsString(
            '<h3>Key features of Sober</h3>',
            $draft['enLong']
        );
        self::assertStringNotContainsString(
            '<li>Ideal for fashion brands.</li>',
            $draft['enLong']
        );
        self::assertStringContainsString(
            '<h3>Кому подходит Sober?</h3>',
            $draft['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Who is Sober for?</h3>',
            $draft['enLong']
        );
    }
}
