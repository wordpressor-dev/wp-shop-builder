<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use ZipArchive;

final class VendorProductNamingAuditServiceTest extends TestCase
{
    public function testAuditsOnlyPublishedVendorProductsAndNeverMarketplaceProducts(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $uploadsDir = sys_get_temp_dir()
            . '/wp-shop-naming-audit-'
            . bin2hex(random_bytes(4));
        $packageDir = $uploadsDir . '/woocommerce_uploads/vendor';
        self::assertTrue(mkdir($packageDir, 0777, true));

        $gutenBricksPath = $packageDir . '/gutenbricks.zip';
        $elementorPath = $packageDir . '/elementor.zip';
        $this->createPluginZip(
            $gutenBricksPath,
            'GutenBricks',
            '1.1.29'
        );
        $this->createPluginZip(
            $elementorPath,
            'Elementor Website Builder – more than just a page builder',
            '3.30.0'
        );

        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            $uploadsDir,
            &$writes
        ): mixed {
            if ($name === 'get_posts') {
                return [10, 20, 30, 40];
            }

            if ($name === 'wp_upload_dir') {
                return [
                    'basedir' => $uploadsDir,
                    'baseurl' => 'https://wp-shop.test/wp-content/uploads',
                ];
            }

            if ($name === 'get_post_field') {
                return match ((int) ($arguments[1] ?? 0)) {
                    10 => 'GutenBricks 1.1.29',
                    20 => 'Marketplace Product',
                    30 => 'Elementor Website Builder – more than just a page '
                        . 'builder 3.30.0',
                    40 => 'Envato Elements Product',
                    default => '',
                };
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                $meta = [
                    10 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://gutenbricks.com/',
                        'attr_version_value' => '1.1.29',
                        '_wp_shop_product_type' => 'plugin',
                        '_downloadable_files' => [
                            'a' => [
                                'file' => 'https://wp-shop.test/wp-content/uploads/'
                                    . 'woocommerce_uploads/vendor/gutenbricks.zip',
                            ],
                        ],
                    ],
                    20 => [
                        '_wp_shop_source_type' => 'envato',
                        'sales_page' => 'https://codecanyon.net/item/example/123',
                    ],
                    30 => [
                        '_wp_shop_source_type' => '',
                        'sales_page' => 'https://elementor.com/',
                        'attr_version_value' => '3.30.0',
                        '_wp_shop_product_type' => 'plugin',
                        '_downloadable_files' => [
                            'b' => [
                                'file' => 'https://wp-shop.test/wp-content/uploads/'
                                    . 'woocommerce_uploads/vendor/elementor.zip',
                            ],
                        ],
                    ],
                    40 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://elements.envato.com/example',
                    ],
                ];

                return $meta[$productId][$key] ?? '';
            }

            if (
                str_starts_with($name, 'update_')
                || $name === 'wp_update_post'
                || $name === 'delete_post_meta'
            ) {
                $writes[] = $name;
            }

            return null;
        };

        try {
            $audit = new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            );

            self::assertSame(2, $audit->candidateCount());
            $rows = $audit->scan(0, 25);

            self::assertCount(2, $rows);
            self::assertSame(10, $rows[0]->productId);
            self::assertSame('RENAME', $rows[0]->action);
            self::assertSame('HIGH', $rows[0]->confidence);
            self::assertSame('GutenBricks', $rows[0]->recommendedTitle);
            self::assertSame('ZIP_PLUGIN_NAME', $rows[0]->evidence);

            self::assertSame(30, $rows[1]->productId);
            self::assertSame('REVIEW', $rows[1]->action);
            self::assertSame('MEDIUM', $rows[1]->confidence);
            self::assertSame(
                'Elementor Website Builder',
                $rows[1]->recommendedTitle
            );
            self::assertSame([], $writes);
        } finally {
            @unlink($gutenBricksPath);
            @unlink($elementorPath);
            @rmdir($packageDir);
            @rmdir(dirname($packageDir));
            @rmdir($uploadsDir);
        }
    }

    private function createPluginZip(
        string $path,
        string $name,
        string $version
    ): void {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE) === true);
        $zip->addFromString(
            'plugin/plugin.php',
            "<?php\n/*\nPlugin Name: "
            . $name
            . "\nVersion: "
            . $version
            . "\n*/\n"
        );
        $zip->close();
    }
}
