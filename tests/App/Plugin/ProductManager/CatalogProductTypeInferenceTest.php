<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class CatalogProductTypeInferenceTest extends TestCase
{
    public function testWoodMartThemeWinsOverPluginMentionsInDescription(): void
    {
        $type = CatalogProductType::infer(
            'WoodMart – Responsive WooCommerce WordPress Theme',
            '',
            'Премиум-тема для WooCommerce. Совместима с популярными плагинами WordPress.'
        );

        self::assertSame(CatalogProductType::THEME, $type);
    }

    public function testWpRocketPluginWinsOverThemeMentionsInDescription(): void
    {
        $type = CatalogProductType::infer(
            'WP Rocket – The Best WordPress Performance Plugin',
            '',
            'Работает с популярными темами WordPress.'
        );

        self::assertSame(CatalogProductType::PLUGIN, $type);
    }

    public function testThemeForestHostIsAuthoritativeWhenTitleIsAmbiguous(): void
    {
        $type = CatalogProductType::infer(
            'WoodMart',
            'https://themeforest.net/item/woodmart-woocommerce-wordpress-theme/20264492',
            'Совместима с популярными плагинами.'
        );

        self::assertSame(CatalogProductType::THEME, $type);
    }

    public function testPluralCompatibilityMentionDoesNotBecomePluginType(): void
    {
        $type = CatalogProductType::infer(
            'Ambiguous Product',
            '',
            'Совместимость с популярными плагинами и расширениями.'
        );

        self::assertSame('', $type);
    }

    public function testTemplateKitStillHasHighestPriority(): void
    {
        $type = CatalogProductType::infer(
            'Education Template Kit for Elementor',
            'https://themeforest.net/item/example/123',
            'Includes templates.'
        );

        self::assertSame(CatalogProductType::TEMPLATE_KIT, $type);
    }
}
