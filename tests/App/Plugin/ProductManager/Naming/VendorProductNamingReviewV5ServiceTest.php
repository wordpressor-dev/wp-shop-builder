<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Naming\TranslatePressTitleInspector;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingReviewV5Service;
use WPShop\App\Plugin\ProductManager\Naming\VendorSalesPageNameInspector;
use ZipArchive;

final class VendorProductNamingReviewV5ServiceTest extends TestCase
{
    public function testSeparatesPackageStatusAndRejectsPartialHeaderName(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $uploadsDir = sys_get_temp_dir()
            . '/wp-shop-naming-review-v5-'
            . bin2hex(random_bytes(4));
        $packageDir = $uploadsDir . '/woocommerce_uploads/vendor';
        self::assertTrue(mkdir($packageDir, 0777, true));
        $yoothemePath = $packageDir . '/yootheme.zip';
        $this->createThemeZip(
            $yoothemePath,
            'YOOtheme',
            '4.5.0'
        );

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            $uploadsDir
        ): mixed {
            if ($name === 'get_posts') {
                return [10, 20];
            }

            if ($name === 'wp_upload_dir') {
                return [
                    'basedir' => $uploadsDir,
                    'baseurl' => 'https://wp-shop.test/wp-content/uploads',
                ];
            }

            if ($name === 'get_post_field') {
                return match ((int) ($arguments[1] ?? 0)) {
                    10 => 'YOOtheme Pro WordPress Theme',
                    20 => 'Yoast SEO Premium – Advanced SEO With AI',
                    default => '',
                };
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                $meta = [
                    10 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://vendor.test/yootheme',
                        '_wp_shop_product_type' => 'theme',
                        '_downloadable_files' => [
                            'a' => [
                                'file' => 'https://wp-shop.test/wp-content/uploads/'
                                    . 'woocommerce_uploads/vendor/yootheme.zip',
                            ],
                        ],
                    ],
                    20 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://vendor.test/yoast',
                        '_wp_shop_product_type' => 'plugin',
                        '_downloadable_files' => [],
                    ],
                ];

                return $meta[$productId][$key] ?? '';
            }

            if ($name === 'wp_remote_get') {
                $url = (string) ($arguments[0] ?? '');

                if ($url === 'https://vendor.test/yootheme') {
                    return [
                        'code' => 200,
                        'body' => '<html><head><title>YOOtheme Pro features</title></head>'
                            . '<body><h1>Features</h1></body></html>',
                    ];
                }

                return [
                    'code' => 200,
                    'body' => '<html><head><title>Yoast SEO Premium • Yoast</title></head>'
                        . '<body><h1>Yoast SEO Premium</h1></body></html>',
                ];
            }

            if ($name === 'wp_remote_retrieve_response_code') {
                $response = (array) ($arguments[0] ?? []);

                return (int) ($response['code'] ?? 0);
            }

            if ($name === 'wp_remote_retrieve_body') {
                $response = (array) ($arguments[0] ?? []);

                return (string) ($response['body'] ?? '');
            }

            return null;
        };

        $database = $this->createMock(
            DatabaseConnectionInterface::class
        );
        $database->method('fetchOne')->willReturn(null);

        try {
            $audit = new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            );
            $review = new VendorProductNamingReviewV5Service(
                $audit,
                new VendorSalesPageNameInspector($call(...)),
                new TranslatePressTitleInspector($database),
                $call(...)
            );

            self::assertSame(2, $review->candidateCount());
            $rows = $review->scan(0, 10);

            self::assertCount(2, $rows);
            self::assertSame(10, $rows[0]->productId);
            self::assertSame('OK', $rows[0]->packageStatus);
            self::assertSame('MANUAL_REVIEW', $rows[0]->namingAction);
            self::assertSame(
                'YOOtheme Pro WordPress Theme',
                $rows[0]->recommendedTitle
            );

            self::assertSame(20, $rows[1]->productId);
            self::assertSame('ZIP_MISSING', $rows[1]->packageStatus);
            self::assertSame(
                'RENAME_CANDIDATE',
                $rows[1]->namingAction
            );
            self::assertSame('HIGH', $rows[1]->confidence);
            self::assertSame(
                'Yoast SEO Premium',
                $rows[1]->recommendedTitle
            );
        } finally {
            @unlink($yoothemePath);
            @rmdir($packageDir);
            @rmdir(dirname($packageDir));
            @rmdir($uploadsDir);
        }
    }

    private function createThemeZip(
        string $path,
        string $name,
        string $version
    ): void {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE) === true);
        $zip->addFromString(
            'theme/style.css',
            "/*\nTheme Name: "
            . $name
            . "\nVersion: "
            . $version
            . "\n*/\n"
        );
        $zip->close();
    }
}
