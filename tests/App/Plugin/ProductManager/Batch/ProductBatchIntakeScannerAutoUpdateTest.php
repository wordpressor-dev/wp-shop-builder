<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Batch;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveVersionInspector;
use ZipArchive;

final class ProductBatchIntakeScannerAutoUpdateTest extends TestCase
{
    public function testScanBlocksDowngradeZipForExistingProduct(): void
    {
        $baseDir = $this->uploadsBaseDir();
        $folder = 'batch-test';
        $directory = $baseDir
            . '/woocommerce_uploads/INBOX/'
            . $folder;
        mkdir($directory, 0777, true);
        $filename = 'themeforest-123456-demo-theme-1.0.0.zip';
        $zipPath = $directory . '/' . $filename;
        $this->themeZip($zipPath, 'Demo Theme', '1.0.0');

        try {
            $scanner = $this->scanner('2.0.0');
            $rows = $scanner->scan($baseDir, $folder);
        } finally {
            $this->removeTree($baseDir);
        }

        self::assertCount(1, $rows);
        self::assertSame('UPDATE', $rows[0]['action']);
        self::assertSame('STOP', $rows[0]['status']);
        self::assertSame(
            'ZIP VERSION OLDER THAN CURRENT; DOWNGRADE BLOCKED',
            $rows[0]['note']
        );
    }

    public function testScanResolvesItemIdFromMatchedProductSalesPage(): void
    {
        $baseDir = $this->uploadsBaseDir();
        $folder = 'identity-match';
        $directory = $baseDir
            . '/woocommerce_uploads/INBOX/'
            . $folder;
        mkdir($directory, 0777, true);
        $filename = 'demo-theme.zip';
        $zipPath = $directory . '/' . $filename;
        $this->themeZip($zipPath, 'Demo Theme', '2.0.0');
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [100];
            }

            if ($name === 'get_the_title') {
                return 'Demo Theme 1.0.0';
            }

            if ($name === 'get_post_meta') {
                return match ((string) ($arguments[1] ?? '')) {
                    '_wp_shop_product_type' => 'theme',
                    'attr_version_value' => '1.0.0',
                    '_wp_shop_source_item_id' => '',
                    'sales_page' => 'https://themeforest.net/item/demo-theme/123456',
                    '_sku' => 'themeforest-123456-demo-theme-1.0.0.zip',
                    default => '',
                };
            }

            return null;
        };
        $scanner = new ProductBatchIntakeScanner(
            $call(...),
            new ProductArchiveVersionInspector(),
            new ProductArchiveIdentityInspector()
        );

        try {
            $rows = $scanner->scan($baseDir, $folder);
        } finally {
            $this->removeTree($baseDir);
        }

        self::assertCount(1, $rows);
        self::assertSame(100, $rows[0]['productId']);
        self::assertSame(123456, $rows[0]['itemId']);
        self::assertSame('UPDATE', $rows[0]['action']);
        self::assertSame('READY', $rows[0]['status']);
        self::assertSame('2.0.0', $rows[0]['detectedVersion']);
    }

    public function testReadyUpdateRowsKeepsOnlyReadyExistingUpdates(): void
    {
        $scanner = $this->scanner('1.0.0');
        $rows = [
            $this->row('a.zip', 'UPDATE', 'READY'),
            $this->row('b.zip', 'CREATE', 'READY'),
            $this->row('c.zip', 'UPDATE', 'STOP'),
        ];

        $ready = $scanner->readyUpdateRows($rows);

        self::assertCount(1, $ready);
        self::assertSame('a.zip', $ready[0]['filename']);
    }

    private function scanner(string $currentVersion): ProductBatchIntakeScanner
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use ($currentVersion): mixed {
            if ($name === 'get_posts') {
                return [100];
            }

            if ($name === 'get_the_title') {
                return 'Demo Theme ' . $currentVersion;
            }

            if ($name === 'get_post_meta') {
                return match ((string) ($arguments[1] ?? '')) {
                    '_wp_shop_product_type' => 'theme',
                    'attr_version_value' => $currentVersion,
                    '_wp_shop_source_item_id' => '123456',
                    '_sku' => 'themeforest-123456-demo-theme-'
                        . $currentVersion . '.zip',
                    default => '',
                };
            }

            return null;
        };

        return new ProductBatchIntakeScanner(
            $call(...),
            new ProductArchiveVersionInspector(),
            new ProductArchiveIdentityInspector()
        );
    }

    /**
     * @return array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }
     */
    private function row(
        string $filename,
        string $action,
        string $status
    ): array {
        return [
            'filename' => $filename,
            'relativePath' => $filename,
            'itemId' => 123456,
            'productId' => 100,
            'productTitle' => 'Demo Theme',
            'productType' => 'theme',
            'currentVersion' => '1.0.0',
            'detectedVersion' => '2.0.0',
            'action' => $action,
            'status' => $status,
            'note' => '',
        ];
    }

    private function uploadsBaseDir(): string
    {
        $path = sys_get_temp_dir()
            . '/wp-shop-intake-'
            . bin2hex(random_bytes(8));
        mkdir($path, 0777, true);

        return $path;
    }

    private function themeZip(
        string $path,
        string $name,
        string $version
    ): void {
        if (! class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive is required.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            self::fail('Unable to create test ZIP.');
        }

        $zip->addFromString(
            'demo-theme/style.css',
            "/*\nTheme Name: {$name}\nVersion: {$version}\n*/\n"
        );
        $zip->close();
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($target)) {
                $this->removeTree($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($path);
    }
}
