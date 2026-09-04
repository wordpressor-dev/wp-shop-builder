<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveUpdateCoordinator;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveVersionInspector;
use WPShop\App\Plugin\ProductManager\Update\ProductBatchZipUpdateService;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use ZipArchive;

final class ProductBatchZipUpdateServiceTest extends TestCase
{
    public function testPreflightMarksValidNewerZipReady(): void
    {
        $service = $this->service();
        $zip = $this->themeZip('1.1.0');

        try {
            $rows = $service->preflight(
                $this->queueRows(),
                [5034],
                [
                    5034 => [
                        'name' => 'veera.zip',
                        'tmp_name' => $zip,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($zip),
                    ],
                ]
            );
        } finally {
            @unlink($zip);
        }

        self::assertSame('READY', $rows[5034]['status']);
        self::assertNotSame('', $rows[5034]['sha256']);
        self::assertContains(
            'BATCH PREFLIGHT = READY',
            $rows[5034]['logs']
        );
    }

    public function testPreflightRejectsZipThatIsNotNewer(): void
    {
        $service = $this->service();
        $zip = $this->themeZip('0.9.0');

        try {
            $rows = $service->preflight(
                $this->queueRows(),
                [5034],
                [
                    5034 => [
                        'name' => 'veera.zip',
                        'tmp_name' => $zip,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($zip),
                    ],
                ]
            );
        } finally {
            @unlink($zip);
        }

        self::assertSame('STOP', $rows[5034]['status']);
        self::assertContains(
            'ZIP VERSION MUST BE NEWER THAN CURRENT VERSION',
            $rows[5034]['logs']
        );
    }

    public function testBatchLimitIsTenProducts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Batch ZIP Update is limited to 10 products per run.'
        );

        $this->service()->preflight(
            [],
            range(1, 11),
            []
        );
    }

    private function service(): ProductBatchZipUpdateService
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_post_type') {
                return 'product';
            }

            if ($name === 'get_post_status') {
                return 'publish';
            }

            if ($name === 'get_post_field') {
                return match ((string) ($arguments[0] ?? '')) {
                    'post_title' => 'Veera – Multipurpose WooCommerce Theme 1.0.0',
                    'post_date' => '2026-08-20 12:00:00',
                    default => '',
                };
            }

            if ($name === 'get_post_meta') {
                return match ((string) ($arguments[1] ?? '')) {
                    'attr_version_value' => '1.0.0',
                    'sales_page' => 'https://themeforest.net/item/'
                        . 'veera-multipurpose-woocommerce-theme/22380037',
                    '_wp_shop_source_item_id' => '22380037',
                    '_wp_shop_source_update_date' => '2026-08-20',
                    '_sku' => 'themeforest-22380037-veera-'
                        . 'multipurpose-woocommerce-theme-1.0.0.zip',
                    '_downloadable_files' => [
                        'old' => [
                            'name' => 'old.zip',
                            'file' => 'https://wp-shop.org/old.zip',
                        ],
                    ],
                    default => '',
                };
            }

            if ($name === 'wc_get_product_id_by_sku') {
                return 5034;
            }

            return null;
        };

        $updater = new ProductVersionUpdater($call(...));
        $uploader = new ProductArchiveUploader(
            static fn (string $name, mixed ...$arguments): mixed => null
        );
        $coordinator = new ProductArchiveUpdateCoordinator(
            $updater,
            $uploader,
            new ProductArchiveVersionInspector()
        );

        return new ProductBatchZipUpdateService(
            $updater,
            $coordinator
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queueRows(): array
    {
        return [
            5034 => [
                'productId' => 5034,
                'title' => 'Veera – Multipurpose WooCommerce Theme 1.0.0',
                'currentVersion' => '1.0.0',
                'envatoVersion' => '1.1.0',
                'envatoUpdateDate' => '2026-09-01',
                'status' => 'UPDATE_AVAILABLE',
                'message' => 'Newer Envato version found.',
            ],
        ];
    }

    private function themeZip(string $version): string
    {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $path = tempnam(sys_get_temp_dir(), 'wp-shop-batch-');

        if ($path === false) {
            self::fail('Unable to create temporary ZIP path.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            self::fail('Unable to create temporary ZIP.');
        }

        $zip->addFromString(
            'veera/style.css',
            "/*\nTheme Name: Veera\nVersion: {$version}\n*/\n"
        );
        $zip->close();

        return $path;
    }
}
