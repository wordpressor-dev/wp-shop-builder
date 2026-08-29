<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

final class ProductDraftDataEditorialTest extends TestCase
{
    public function testImportQueueDraftReplacesPlaceholderEditorialContent(): void
    {
        $data = $this->data(
            'Created from WP Shop Builder Import Queue. Review before publish.'
        );

        self::assertStringContainsString(
            'виллы, отели и курорты',
            $data->shortDescription
        );
        self::assertStringContainsString(
            '<h3>Назначение и основные сведения</h3>',
            $data->longDescription
        );
        self::assertStringContainsString(
            'villa, hotel and resort',
            $data->enShortDescription
        );
        self::assertStringContainsString(
            '<h3>Purpose and product details</h3>',
            $data->enLongDescription
        );
        self::assertNotSame('RU meta placeholder', $data->metaDescription);
        self::assertNotSame('EN meta placeholder', $data->enMetaDescription);
    }

    public function testManualDraftKeepsEditorialContentUntouched(): void
    {
        $data = $this->data('Manual product draft.');

        self::assertSame('RU short placeholder', $data->shortDescription);
        self::assertSame('RU long placeholder', $data->longDescription);
        self::assertSame('RU meta placeholder', $data->metaDescription);
        self::assertSame('EN short placeholder', $data->enShortDescription);
        self::assertSame('EN long placeholder', $data->enLongDescription);
        self::assertSame('EN meta placeholder', $data->enMetaDescription);
    }

    private function data(string $notes): ProductDraftData
    {
        return new ProductDraftData(
            'Villora – Villa & Hotel Resort Elementor Template Kit',
            'villora',
            64256262,
            '',
            '2026-07-15',
            'kitpixel',
            '249',
            'https://themeforest.net/item/villora-villa-hotel-resort-elementor-template-kit/64256262',
            'themeforest-64256262-villora-villa-hotel-resort-elementor-template-kit.zip',
            'https://wp-shop.org/example.zip',
            0,
            [],
            'RU short placeholder',
            'RU long placeholder',
            'RU meta placeholder',
            'EN short placeholder',
            'EN long placeholder',
            'EN meta placeholder',
            $notes,
            false,
            false
        );
    }
}
