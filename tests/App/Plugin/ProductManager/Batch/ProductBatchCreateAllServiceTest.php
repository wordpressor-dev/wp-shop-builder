<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Batch;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchCreateAllService;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;

final class ProductBatchCreateAllServiceTest extends TestCase
{
    public function testPrepareUsesKnownItemIdAndUserReferences(): void
    {
        $service = $this->service();
        $prepared = $service->prepare(
            [
                $this->row('known.zip', 0, 123456, 'theme'),
                $this->row('manual.zip', 0, 0, 'plugin'),
                $this->row('missing.zip', 0, 0, 'theme'),
                $this->row('existing.zip', 50, 999, 'theme'),
                $this->row('package.zip', 0, 0, ''),
            ],
            [
                'manual.zip' => 'https://codecanyon.net/item/manual/654321',
                'missing.zip' => '',
            ],
            [
                'known.zip' => '⚠ Продукт предварительно активирован.',
                'manual.zip' => '',
            ],
            [
                'known.zip' => 'envato',
                'manual.zip' => 'envato',
                'missing.zip' => 'envato',
            ]
        );

        self::assertSame(
            [
                [
                    'filename' => 'known.zip',
                    'reference' => '123456',
                    'notes' => '⚠ Продукт предварительно активирован.',
                    'sourceType' => 'envato',
                ],
                [
                    'filename' => 'manual.zip',
                    'reference' => 'https://codecanyon.net/item/manual/654321',
                    'notes' => '',
                    'sourceType' => 'envato',
                ],
            ],
            $prepared['entries']
        );
        self::assertSame(['missing.zip'], $prepared['missing']);
    }

    public function testPrepareAllowsVendorWithoutEnvatoReference(): void
    {
        $service = $this->service();
        $prepared = $service->prepare(
            [
                $this->row('vendor.zip', 0, 0, 'plugin'),
            ],
            [
                'vendor.zip' => '',
            ],
            [
                'vendor.zip' => '',
            ],
            [
                'vendor.zip' => 'vendor',
            ]
        );

        self::assertSame(
            [
                [
                    'filename' => 'vendor.zip',
                    'reference' => '',
                    'notes' => '',
                    'sourceType' => 'vendor',
                ],
            ],
            $prepared['entries']
        );
        self::assertSame([], $prepared['missing']);
    }

    public function testProcessContinuesAfterFailureAndReturnsRemainingQueue(): void
    {
        $created = [];
        $reviewed = [];
        $service = new ProductBatchCreateAllService(
            static function (
                string $uploadsBaseDir,
                string $folder,
                string $filename,
                string $reference,
                string $notes,
                string $sourceType
            ) use (&$created): ProductDraftResult {
                unset(
                    $uploadsBaseDir,
                    $folder,
                    $reference,
                    $notes,
                    $sourceType
                );

                if ($filename === 'bad.zip') {
                    return new ProductDraftResult(
                        false,
                        null,
                        ['CREATE FAILED']
                    );
                }

                $created[] = $filename;

                return new ProductDraftResult(
                    true,
                    100 + count($created),
                    ['CREATE READY']
                );
            },
            static function (
                string $uploadsBaseDir,
                string $folder,
                string $filename
            ) use (&$reviewed): string {
                unset($uploadsBaseDir, $folder);
                $reviewed[] = $filename;

                return '_REVIEW/' . $filename;
            }
        );
        $entries = [];

        for ($index = 1; $index <= 12; ++$index) {
            $entries[] = [
                'filename' => $index === 2
                    ? 'bad.zip'
                    : 'new-' . $index . '.zip',
                'reference' => (string) (1000 + $index),
                'notes' => $index === 1
                    ? '⚠ Продукт предварительно активирован.'
                    : '',
                'sourceType' => 'envato',
            ];
        }

        $result = $service->process(
            '/uploads',
            'batch',
            $entries,
            10
        );

        self::assertSame(10, $result['processed']);
        self::assertSame(9, $result['created']);
        self::assertSame(1, $result['failed']);
        self::assertSame(['bad.zip'], $reviewed);
        self::assertCount(2, $result['remaining']);
        self::assertTrue($result['continue']);
        self::assertCount(9, $result['productIds']);
        self::assertContains(
            'AUTO CREATE CONTINUE = REQUIRED',
            $result['logs']
        );
    }

    private function service(): ProductBatchCreateAllService
    {
        return new ProductBatchCreateAllService(
            static fn (
                string $uploadsBaseDir,
                string $folder,
                string $filename,
                string $reference,
                string $notes,
                string $sourceType
            ): ProductDraftResult => new ProductDraftResult(
                true,
                1,
                [
                    $uploadsBaseDir,
                    $folder,
                    $filename,
                    $reference,
                    $notes,
                    $sourceType,
                ]
            ),
            static fn (
                string $uploadsBaseDir,
                string $folder,
                string $filename
            ): string => $uploadsBaseDir
                . '/'
                . $folder
                . '/'
                . $filename
        );
    }

    /**
     * @return array{
     *   filename:string,
     *   relativePath:string,
     *   itemId:int,
     *   productId:int,
     *   productTitle:string,
     *   productType:string,
     *   currentVersion:string,
     *   detectedVersion:string,
     *   action:string,
     *   status:string,
     *   note:string
     * }
     */
    private function row(
        string $filename,
        int $productId,
        int $itemId,
        string $productType
    ): array {
        return [
            'filename' => $filename,
            'relativePath' => $filename,
            'itemId' => $itemId,
            'productId' => $productId,
            'productTitle' => '',
            'productType' => $productType,
            'currentVersion' => '',
            'detectedVersion' => '1.0.0',
            'action' => $productId > 0 ? 'UPDATE' : 'REVIEW',
            'status' => 'READY',
            'note' => '',
        ];
    }
}
