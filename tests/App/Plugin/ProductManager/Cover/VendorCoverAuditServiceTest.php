<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Cover;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverAuditService;

final class VendorCoverAuditServiceTest extends TestCase
{
    public function testClassifiesVendorCoversAndProtectsMarketplace(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [10, 20, 30];
            }

            if ($name === 'get_post_field') {
                return match ((int) ($arguments[1] ?? 0)) {
                    10 => 'Vendor Without Cover',
                    20 => 'Vendor Standard Cover',
                    30 => 'Marketplace Theme',
                    default => '',
                };
            }

            if ($name === 'get_post_thumbnail_id') {
                return match ((int) ($arguments[0] ?? 0)) {
                    20 => 200,
                    30 => 300,
                    default => 0,
                };
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                $meta = [
                    10 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://vendor.test/product',
                        '_wp_shop_source_item_id' => 0,
                        '_sku' => 'vendor-product.zip',
                        '_wp_shop_vendor_cover_generated' => '',
                        '_wp_shop_vendor_cover_lock' => '',
                    ],
                    20 => [
                        '_wp_shop_source_type' => 'vendor',
                        'sales_page' => 'https://vendor.test/standard',
                        '_wp_shop_source_item_id' => 0,
                        '_sku' => 'vendor-standard.zip',
                        '_wp_shop_vendor_cover_generated' => '1',
                        '_wp_shop_vendor_cover_lock' => '',
                    ],
                    30 => [
                        '_wp_shop_source_type' => 'envato',
                        'sales_page' => 'https://themeforest.net/item/example/123',
                        '_wp_shop_source_item_id' => 123,
                        '_sku' => 'themeforest-123-example.zip',
                        '_wp_shop_vendor_cover_generated' => '',
                        '_wp_shop_vendor_cover_lock' => '',
                    ],
                ];

                return $meta[$productId][$key] ?? '';
            }

            if ($name === 'wp_get_attachment_url') {
                return match ((int) ($arguments[0] ?? 0)) {
                    200 => 'https://wp-shop.test/uploads/vendor-standard.webp',
                    300 => 'https://wp-shop.test/uploads/theme.jpg',
                    default => '',
                };
            }

            if ($name === 'get_attached_file') {
                return match ((int) ($arguments[0] ?? 0)) {
                    200 => '/uploads/vendor-standard.webp',
                    300 => '/uploads/theme.jpg',
                    default => '',
                };
            }

            if ($name === 'wp_get_attachment_metadata') {
                return match ((int) ($arguments[0] ?? 0)) {
                    200 => ['width' => 590, 'height' => 300],
                    300 => ['width' => 800, 'height' => 450],
                    default => [],
                };
            }

            if ($name === 'get_post_mime_type') {
                return match ((int) ($arguments[0] ?? 0)) {
                    200 => 'image/webp',
                    300 => 'image/jpeg',
                    default => '',
                };
            }

            return null;
        };

        $audit = new VendorCoverAuditService($call(...));

        self::assertSame(3, $audit->candidateCount());

        $rows = $audit->scanAll();

        self::assertCount(3, $rows);

        self::assertSame(10, $rows[0]->productId);
        self::assertSame('GENERATE', $rows[0]->action);
        self::assertSame('NONE', $rows[0]->imageStatus);

        self::assertSame(20, $rows[1]->productId);
        self::assertSame('KEEP', $rows[1]->action);
        self::assertTrue($rows[1]->standardMatch);
        self::assertSame('STANDARD', $rows[1]->imageStatus);

        self::assertSame(30, $rows[2]->productId);
        self::assertSame('SKIP_MARKETPLACE', $rows[2]->action);
        self::assertTrue($rows[2]->marketplaceProtected);
    }
}
