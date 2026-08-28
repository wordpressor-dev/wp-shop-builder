<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Envato;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;

final class EnvatoItemMapperTest extends TestCase
{
    public function testMapsThemeForestItemIntoProductFields(): void
    {
        $mapper = new EnvatoItemMapper();

        $item = $mapper->map(
            [
                'id' => 26350912,
                'name' => 'Aabbe - Digital Marketplace WordPress Theme',
                'url' => 'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
                'author_username' => 'QuomodoTheme',
                'number_of_sales' => 205,
                'published_at' => '2020-05-12T20:41:58+10:00',
                'updated_at' => '2025-04-20T20:51:36+10:00',
                'wordpress_theme_metadata' => [
                    'version' => '5.0.0',
                ],
                'tags' => [
                    'audio marketplace',
                    'digital marketplace',
                    'multi vendors',
                ],
                'thumbnail_url' => 'https://assets.market.envato.com/thumb.jpg',
                'previews' => [
                    'icon_with_landscape_preview' => [
                        'icon_url' => 'https://assets.market.envato.com/icon.jpg',
                        'landscape_url' => 'https://assets.market.envato.com/landscape.jpg',
                    ],
                ],
            ],
            [],
            'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912'
        );

        self::assertSame(26350912, $item->itemId);
        self::assertSame(
            'Aabbe – Digital Marketplace WordPress Theme',
            $item->baseTitle
        );
        self::assertSame('aabbe', $item->productSlug);
        self::assertSame('5.0.0', $item->version);
        self::assertSame('2025-04-20', $item->updatedDate);
        self::assertSame('QuomodoTheme', $item->developer);
        self::assertSame(205, $item->sales);
        self::assertSame(
            'https://assets.market.envato.com/landscape.jpg',
            $item->previewImageUrl
        );
        self::assertSame(
            'themeforest-26350912-aabbe-digital-marketplace-wordpress-theme-5.0.0.zip',
            $item->skuFilename
        );
        self::assertSame(
            [
                'audio marketplace',
                'digital marketplace',
                'multi vendors',
            ],
            $item->tags
        );
    }

    public function testVersionEndpointWinsOverItemMetadata(): void
    {
        $mapper = new EnvatoItemMapper();

        $item = $mapper->map(
            [
                'id' => 14058034,
                'name' => 'Eduma - Education WordPress Theme',
                'url' => 'https://themeforest.net/item/education-wordpress-theme-education-wp/14058034',
                'wordpress_theme_metadata' => [
                    'version' => '5.8.0',
                ],
            ],
            [
                'version' => '5.9.4',
            ],
            'https://themeforest.net/item/education-wordpress-theme-education-wp/14058034'
        );

        self::assertSame('5.9.4', $item->version);
        self::assertSame(
            'themeforest-14058034-education-wordpress-theme-education-wp-5.9.4.zip',
            $item->skuFilename
        );
    }

    public function testPreviewImageFallsBackToThumbnail(): void
    {
        $item = (new EnvatoItemMapper())->map(
            [
                'id' => 43194184,
                'name' => 'EstateRoof - Roofing Services Elementor Pro Template Kit',
                'url' => 'https://themeforest.net/item/estateroof-roofing-services-elementor-pro-template-kit/43194184',
                'thumbnail_url' => 'https://assets.market.envato.com/estateroof-thumb.jpg',
            ],
            [],
            'https://themeforest.net/item/estateroof-roofing-services-elementor-pro-template-kit/43194184'
        );

        self::assertSame('', $item->version);
        self::assertSame(
            'themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip',
            $item->skuFilename
        );
        self::assertSame(
            'https://assets.market.envato.com/estateroof-thumb.jpg',
            $item->previewImageUrl
        );
    }

    public function testRejectsPayloadWithoutItemId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Envato item ID is missing.');

        (new EnvatoItemMapper())->map(
            ['name' => 'Theme'],
            [],
            'https://themeforest.net/item/theme/123'
        );
    }
}
