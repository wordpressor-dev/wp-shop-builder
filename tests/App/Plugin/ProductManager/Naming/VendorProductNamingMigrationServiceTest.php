<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingMigrationService;
use ZipArchive;

final class VendorProductNamingMigrationServiceTest extends TestCase
{
    public function testRemovesOnlyVerifiedDashSuffixAndPreservesSlug(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $uploadsDir = sys_get_temp_dir()
            . '/wp-shop-vendor-naming-migration-'
            . bin2hex(random_bytes(4));
        $packageDir = $uploadsDir . '/woocommerce_uploads/vendor';
        self::assertTrue(mkdir($packageDir, 0777, true));
        $archivePath = $packageDir . '/wp-rocket.zip';
        $this->createPluginZip(
            $archivePath,
            'WP Rocket',
            '3.23.2.2'
        );

        $post = (object) [
            'ID' => 10,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'WP Rocket – The Best WordPress Performance Plugin',
            'post_name' => 'wp-rocket',
        ];
        $writes = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            $uploadsDir,
            &$post,
            &$writes
        ): mixed {
            if ($name === 'get_posts') {
                return [10];
            }

            if ($name === 'wp_upload_dir') {
                return [
                    'basedir' => $uploadsDir,
                    'baseurl' => 'https://wp-shop.test/wp-content/uploads',
                ];
            }

            if ($name === 'get_post') {
                return clone $post;
            }

            if ($name === 'get_post_field') {
                return $post->post_title;
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return match ($key) {
                    '_wp_shop_source_type' => 'vendor',
                    'sales_page' => 'https://wp-rocket.me/',
                    '_wp_shop_source_item_id' => 0,
                    '_sku' => 'wp-rocket-3.23.2.2.zip',
                    'attr_version_value' => '3.23.2.2',
                    '_wp_shop_product_type' => 'plugin',
                    '_downloadable_files' => [
                        'a' => [
                            'file' => 'https://wp-shop.test/wp-content/uploads/'
                                . 'woocommerce_uploads/vendor/wp-rocket.zip',
                        ],
                    ],
                    default => '',
                };
            }

            if ($name === 'wp_update_post') {
                $data = (array) ($arguments[0] ?? []);
                $writes[] = $data;
                $post->post_title = (string) ($data['post_title'] ?? '');
                $post->post_name = (string) ($data['post_name'] ?? '');

                return 10;
            }

            return null;
        };

        try {
            $audit = new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            );
            $migration = new VendorProductNamingMigrationService(
                $audit,
                $call(...)
            );
            $snapshot = [
                'productId' => 10,
                'currentTitle' => 'WP Rocket – The Best WordPress Performance Plugin',
                'recommendedTitle' => 'WP Rocket',
                'action' => 'RENAME',
                'confidence' => 'HIGH',
                'reason' => VendorProductNamingMigrationService::SAFE_REASON,
            ];

            self::assertTrue(
                $migration->eligibleSnapshotRow($snapshot)
            );

            $result = $migration->apply($snapshot);

            self::assertSame('UPDATED', $result['status']);
            self::assertSame('WP Rocket', $result['newTitle']);
            self::assertSame('wp-rocket', $result['slug']);
            self::assertCount(1, $writes);
            self::assertSame('WP Rocket', $writes[0]['post_title']);
            self::assertSame('wp-rocket', $writes[0]['post_name']);
            self::assertArrayNotHasKey('post_content', $writes[0]);
            self::assertArrayNotHasKey('post_excerpt', $writes[0]);
            self::assertArrayNotHasKey('post_status', $writes[0]);
        } finally {
            @unlink($archivePath);
            @rmdir($packageDir);
            @rmdir(dirname($packageDir));
            @rmdir($uploadsDir);
        }
    }

    public function testSkipsIfProductBecomesMarketplaceBeforeWrite(): void
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $writes = [];
        $post = (object) [
            'ID' => 20,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'WP Rocket – The Best WordPress Performance Plugin',
            'post_name' => 'wp-rocket',
        ];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$writes
        ): mixed {
            if ($name === 'get_post') {
                return clone $post;
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return match ($key) {
                    '_wp_shop_source_type' => 'envato',
                    'sales_page' => 'https://codecanyon.net/item/example/20',
                    default => '',
                };
            }

            if ($name === 'wp_update_post') {
                $writes[] = $arguments;

                return 20;
            }

            return null;
        };

        $audit = new VendorProductNamingAuditService(
            $call(...),
            new ProductArchiveIdentityInspector()
        );
        $result = (new VendorProductNamingMigrationService(
            $audit,
            $call(...)
        ))->apply([
            'productId' => 20,
            'currentTitle' => 'WP Rocket – The Best WordPress Performance Plugin',
            'recommendedTitle' => 'WP Rocket',
            'action' => 'RENAME',
            'confidence' => 'HIGH',
            'reason' => VendorProductNamingMigrationService::SAFE_REASON,
        ]);

        self::assertSame('SKIP', $result['status']);
        self::assertSame([], $writes);
        self::assertStringContainsString(
            'no longer classified as Vendor',
            $result['reason']
        );
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
