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
    public function testDetectsMainThemeInsideOuterPackageAndSkipsChildTheme(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $main = tempnam(sys_get_temp_dir(), 'wp-shop-goya-main-');
        $child = tempnam(sys_get_temp_dir(), 'wp-shop-goya-child-');
        $outer = tempnam(sys_get_temp_dir(), 'wp-shop-goya-package-');

        if ($main === false || $child === false || $outer === false) {
            self::fail('Unable to create temp ZIP paths.');
        }

        $mainZip = new ZipArchive();
        self::assertTrue(
            $mainZip->open($main, ZipArchive::OVERWRITE) === true
        );
        $mainZip->addFromString(
            'goya/style.css',
            "/*\n"
            . "Theme Name: Goya\n"
            . "Version: 1.8.9\n"
            . "Author: Euthemians\n"
            . "Theme URI: https://goya.euthemians.com/\n"
            . "*/\n"
        );
        $mainZip->close();

        $childZip = new ZipArchive();
        self::assertTrue(
            $childZip->open($child, ZipArchive::OVERWRITE) === true
        );
        $childZip->addFromString(
            'goya-child/style.css',
            "/*\n"
            . "Theme Name: Goya Child\n"
            . "Version: 1.0.0\n"
            . "Template: goya\n"
            . "Author: Euthemians\n"
            . "*/\n"
        );
        $childZip->close();

        $mainBytes = file_get_contents($main);
        $childBytes = file_get_contents($child);

        self::assertIsString($mainBytes);
        self::assertIsString($childBytes);

        $outerZip = new ZipArchive();
        self::assertTrue(
            $outerZip->open($outer, ZipArchive::OVERWRITE) === true
        );
        $outerZip->addFromString(
            'Documentation/readme.txt',
            'Documentation'
        );
        $outerZip->addFromString(
            'goya-child.zip',
            $childBytes
        );
        $outerZip->addFromString(
            'goya.zip',
            $mainBytes
        );
        $outerZip->close();

        try {
            $result = (new ProductArchiveIdentityInspector())->inspect(
                $outer,
                'goya-package.zip'
            );
        } finally {
            @unlink($main);
            @unlink($child);
            @unlink($outer);
        }

        self::assertTrue($result->success);
        self::assertSame('theme', $result->productType);
        self::assertSame('Goya', $result->name);
        self::assertSame('1.8.9', $result->version);
        self::assertSame('Euthemians', $result->developer);
        self::assertSame(
            'https://goya.euthemians.com/',
            $result->productUrl
        );
        self::assertSame('goya.zip', $result->packageEntry);
        self::assertStringContainsString(
            'nested:goya.zip > goya/style.css',
            $result->source
        );
    }

}
