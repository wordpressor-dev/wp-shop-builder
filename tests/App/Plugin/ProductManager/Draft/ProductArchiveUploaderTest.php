<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;

final class ProductArchiveUploaderTest extends TestCase
{
    public function testStoresTemplateKitWithCanonicalNameAndUrl(): void
    {
        $files = [];
        $uploader = new ProductArchiveUploader(
            $this->fakeCaller($files)
        );

        $result = $uploader->storeForCreate(
            $this->upload('zoya-original.zip'),
            'Zoya – Minimal Blog Elementor Template Kit',
            'https://themeforest.net/item/zoya-minimal-blog-elementor-template-kit/42018723',
            42018723,
            ''
        );

        self::assertTrue($result->success);
        self::assertTrue($result->supplied);
        self::assertSame(
            'themeforest-42018723-zoya-minimal-blog-elementor-template-kit.zip',
            $result->skuFilename
        );
        self::assertSame(
            'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/'
                . 'TEMPLATES/42018723/'
                . 'themeforest-42018723-zoya-minimal-blog-elementor-template-kit.zip',
            $result->downloadUrl
        );
        self::assertArrayHasKey($result->targetPath, $files);
        self::assertSame('new', $files[$result->targetPath]);
    }

    public function testUpdateBacksUpSameCanonicalFileAndCanRollback(): void
    {
        $target = '/srv/uploads/woocommerce_uploads/TEMPLATES/42018723/'
            . 'themeforest-42018723-zoya-minimal-blog-elementor-template-kit.zip';
        $files = [$target => 'old'];
        $uploader = new ProductArchiveUploader(
            $this->fakeCaller($files)
        );

        $result = $uploader->storeForUpdate(
            $this->upload('replacement.zip'),
            'Zoya – Minimal Blog Elementor Template Kit',
            'https://themeforest.net/item/zoya-minimal-blog-elementor-template-kit/42018723',
            42018723,
            ''
        );

        self::assertTrue($result->success);
        self::assertSame('new', $files[$target]);
        self::assertSame(
            'old',
            $files[$target . '.wp-shop-backup']
        );

        $logs = $uploader->rollback($result);

        self::assertSame('old', $files[$target]);
        self::assertArrayNotHasKey(
            $target . '.wp-shop-backup',
            $files
        );
        self::assertContains('ARCHIVE ROLLBACK = READY', $logs);
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(string $name): array
    {
        return [
            'name' => $name,
            'tmp_name' => '/tmp/uploaded-product.zip',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
            'type' => 'application/zip',
        ];
    }

    /**
     * @param array<string, string> $files
     * @return \Closure(string, mixed...): mixed
     */
    private function fakeCaller(array &$files): \Closure
    {
        return static function (
            string $name,
            mixed ...$arguments
        ) use (&$files): mixed {
            return match ($name) {
                'is_uploaded_file' => true,
                'file_get_contents' => "PK\x03\x04",
                'wp_upload_dir' => [
                    'basedir' => '/srv/uploads',
                    'baseurl' => 'https://wp-shop.org/wp-content/uploads',
                    'error' => '',
                ],
                'wp_mkdir_p' => true,
                'file_exists' => isset($files[(string) $arguments[0]]),
                'move_uploaded_file' => self::moveFile(
                    $files,
                    (string) $arguments[1],
                    'new'
                ),
                'rename' => self::renameFile(
                    $files,
                    (string) $arguments[0],
                    (string) $arguments[1]
                ),
                'unlink' => self::unlinkFile(
                    $files,
                    (string) $arguments[0]
                ),
                default => throw new RuntimeException(
                    'Unexpected function call: ' . $name
                ),
            };
        };
    }

    /**
     * @param array<string, string> $files
     */
    private static function moveFile(
        array &$files,
        string $target,
        string $value
    ): bool {
        $files[$target] = $value;

        return true;
    }

    /**
     * @param array<string, string> $files
     */
    private static function renameFile(
        array &$files,
        string $source,
        string $target
    ): bool {
        if (! isset($files[$source])) {
            return false;
        }

        $files[$target] = $files[$source];
        unset($files[$source]);

        return true;
    }

    /**
     * @param array<string, string> $files
     */
    private static function unlinkFile(
        array &$files,
        string $path
    ): bool {
        if (! isset($files[$path])) {
            return false;
        }

        unset($files[$path]);

        return true;
    }
}
