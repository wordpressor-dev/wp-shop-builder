<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;

final class ProductTitlePolicyTest extends TestCase
{
    public function testVendorDraftTitleDoesNotContainVersion(): void
    {
        $data = new ProductDraftData(
            'Elementor Pro',
            'elementor-pro',
            0,
            '3.32.1',
            '2026-09-06',
            'Elementor',
            '0',
            'https://elementor.com/pro/',
            'elementor-pro-3.32.1.zip',
            'https://wp-shop.test/uploads/elementor-pro-3.32.1.zip',
            0,
            [],
            '',
            '',
            '',
            '',
            '',
            '',
            'Vendor draft.',
            false,
            false
        );

        self::assertSame('Elementor Pro', $data->title());
    }

    public function testEnvatoDraftTitleDoesNotContainVersion(): void
    {
        $data = new ProductDraftData(
            'Example Theme',
            'example-theme',
            12345,
            '2.4.0',
            '2026-09-06',
            'Author',
            '59',
            'https://themeforest.net/item/example-theme/12345',
            'themeforest-12345-example-theme-2.4.0.zip',
            'https://wp-shop.test/uploads/example.zip',
            0,
            [],
            '',
            '',
            '',
            '',
            '',
            '',
            'Envato draft.',
            false,
            false
        );

        self::assertSame('Example Theme', $data->title());
    }

    public function testVendorUpdateTitleStaysVersionless(): void
    {
        $data = new ProductUpdateData(
            10,
            'Elementor Pro',
            0,
            '3.32.0',
            '3.32.1',
            '2026-09-06',
            'https://elementor.com/pro/',
            'elementor-pro-3.32.0.zip',
            'elementor-pro-3.32.1.zip',
            'https://wp-shop.test/uploads/elementor-pro-3.32.1.zip',
            'vendor'
        );

        self::assertSame('Elementor Pro', $data->title());
    }

    public function testEnvatoUpdateTitleStaysVersionless(): void
    {
        $data = new ProductUpdateData(
            20,
            'Example Plugin',
            67890,
            '1.0.0',
            '1.1.0',
            '2026-09-06',
            'https://codecanyon.net/item/example-plugin/67890',
            'codecanyon-67890-example-plugin-1.0.0.zip',
            'codecanyon-67890-example-plugin-1.1.0.zip',
            'https://wp-shop.test/uploads/example-plugin.zip',
            'envato'
        );

        self::assertSame('Example Plugin', $data->title());
    }
}
