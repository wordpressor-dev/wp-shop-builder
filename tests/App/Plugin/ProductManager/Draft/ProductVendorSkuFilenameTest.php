<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductVendorSkuFilename;

final class ProductVendorSkuFilenameTest extends TestCase
{
    public function testBuildKeepsOriginalVersionedFilename(): void
    {
        self::assertSame(
            'elementor-pro-4.2.4-package.zip',
            ProductVendorSkuFilename::build(
                'elementor-pro-4.2.4-package.zip',
                'Elementor Pro',
                '4.2.4'
            )
        );
    }

    public function testBuildAddsVersionWhenOriginalFilenameHasNone(): void
    {
        self::assertSame(
            'elementor-pro-4.2.4.zip',
            ProductVendorSkuFilename::build(
                'elementor-pro.zip',
                'Elementor Pro',
                '4.2.4'
            )
        );
    }

    public function testSynchronizesVendorSkuByCurrentVersion(): void
    {
        self::assertSame(
            'elementor-pro-4.2.4-package.zip',
            ProductVendorSkuFilename::synchronize(
                'elementor-pro-4.2.3-package.zip',
                '4.2.3',
                '4.2.4'
            )
        );
    }

    public function testRejectsSkuWithoutCurrentVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Current vendor version was not found exactly once in SKU.'
        );

        ProductVendorSkuFilename::synchronize(
            'elementor-pro-package.zip',
            '4.2.3',
            '4.2.4'
        );
    }
}
