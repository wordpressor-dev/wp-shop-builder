<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class CatalogProductTypeArchiveTest extends TestCase
{
    public function testInfersThemeFromCanonicalThemeForestSku(): void
    {
        self::assertSame(
            CatalogProductType::THEME,
            CatalogProductType::inferArchiveName(
                'themeforest-123456-trendy-travel-wordpress-6.6'
            )
        );
    }

    public function testInfersPluginFromCanonicalCodeCanyonSku(): void
    {
        self::assertSame(
            CatalogProductType::PLUGIN,
            CatalogProductType::inferArchiveName(
                'codecanyon-123456-example-plugin-1.2.3.zip'
            )
        );
    }

    public function testTemplateKitMarkerWinsOverThemeForestPrefix(): void
    {
        self::assertSame(
            CatalogProductType::TEMPLATE_KIT,
            CatalogProductType::inferArchiveName(
                'themeforest-123456-education-template-kit.zip'
            )
        );
    }

    public function testNonEnvatoAddonSkuDoesNotPretendToBeTheme(): void
    {
        self::assertSame(
            '',
            CatalogProductType::inferArchiveName('generatepress-premium-2.5.6')
        );
    }
}
