<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Batch;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use ZipArchive;

final class ProductArchiveIdentityInspectorVendorTest extends TestCase
{
    public function testReadsVendorMetadataFromPluginHeader(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $path = tempnam(sys_get_temp_dir(), 'wp-shop-vendor-');

        if ($path === false) {
            self::fail('Unable to create temp ZIP path.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            self::fail('Unable to create ZIP.');
        }

        $zip->addFromString(
            'elementor-pro/elementor-pro.php',
            "<?php\n"
            . "/*\n"
            . "Plugin Name: Elementor Pro\n"
            . "Version: 4.2.4\n"
            . "Author: Elementor\n"
            . "Plugin URI: https://elementor.com/pro/\n"
            . "*/\n"
        );
        $zip->close();

        try {
            $result = (new ProductArchiveIdentityInspector())->inspect(
                $path,
                'elementor-pro.zip'
            );
        } finally {
            @unlink($path);
        }

        self::assertTrue($result->success);
        self::assertSame('plugin', $result->productType);
        self::assertSame('Elementor Pro', $result->name);
        self::assertSame('4.2.4', $result->version);
        self::assertSame('Elementor', $result->developer);
        self::assertSame(
            'https://elementor.com/pro/',
            $result->productUrl
        );
    }
}
