<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductDownloadUrl;

final class ProductDownloadUrlTest extends TestCase
{
    public function testBuildsTemplateKitUrlUnderTemplatesStorage(): void
    {
        $expected = 'https://wp-shop.org/wp-content/uploads/'
            . 'woocommerce_uploads/TEMPLATES/42018723/'
            . 'themeforest-42018723-zoya-minimal-blog-'
            . 'elementor-template-kit.zip';

        self::assertSame(
            $expected,
            ProductDownloadUrl::build(
                'https://wp-shop.org/wp-content/uploads/',
                CatalogProductType::TEMPLATE_KIT,
                42018723,
                'themeforest-42018723-zoya-minimal-blog-elementor-template-kit.zip'
            )
        );
    }

    public function testBuildsThemeAndPluginStorageUrls(): void
    {
        self::assertStringContainsString(
            '/woocommerce_uploads/THEMES/14058034/',
            ProductDownloadUrl::build(
                'https://example.test/wp-content/uploads',
                CatalogProductType::THEME,
                14058034,
                'themeforest-14058034-eduma-5.9.4.zip'
            )
        );
        self::assertStringContainsString(
            '/woocommerce_uploads/PLUGINS/123/',
            ProductDownloadUrl::build(
                'https://example.test/wp-content/uploads',
                CatalogProductType::PLUGIN,
                123,
                'codecanyon-123-plugin-1.0.0.zip'
            )
        );
    }

    public function testRejectsUnsafeOrIncompleteInput(): void
    {
        self::assertSame(
            '',
            ProductDownloadUrl::build(
                'not-a-url',
                CatalogProductType::THEME,
                123,
                'theme.zip'
            )
        );
        self::assertSame(
            '',
            ProductDownloadUrl::build(
                'https://example.test/uploads',
                CatalogProductType::THEME,
                0,
                'theme.zip'
            )
        );
        self::assertSame(
            '',
            ProductDownloadUrl::build(
                'https://example.test/uploads',
                CatalogProductType::THEME,
                123,
                '../theme.zip'
            )
        );
    }
}
