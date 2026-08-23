<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;

final class WordPressWooCommerceDraftGatewayTest extends TestCase
{
    public function testFindsExistingProductBySlugWithoutAttachments(): void
    {
        $capturedArgs = [];
        $gateway = new WordPressWooCommerceDraftGateway(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$capturedArgs): mixed {
                $capturedArgs[$name] = $arguments;

                return match ($name) {
                    'get_posts' => [5028],
                    'get_post_status' => 'draft',
                    default => null,
                };
            }
        );

        $product = $gateway->findBySlug('aabbe');

        self::assertNotNull($product);
        self::assertSame(5028, $product->id);
        self::assertSame('draft', $product->status);
        self::assertSame(
            'product',
            $capturedArgs['get_posts'][0]['post_type']
        );
        self::assertNotContains(
            'trash',
            $capturedArgs['get_posts'][0]['post_status']
        );
    }

    public function testSkuLookupIncludesTrashedProduct(): void
    {
        $gateway = new WordPressWooCommerceDraftGateway(
            static function (
                string $name,
                mixed ...$arguments
            ): mixed {
                return match ($name) {
                    'wc_get_product_id_by_sku' => 5027,
                    'get_post_status' => 'trash',
                    default => null,
                };
            }
        );

        $product = $gateway->findBySku(
            'themeforest-26350912-aabbe-6.2.0.zip'
        );

        self::assertNotNull($product);
        self::assertSame(5027, $product->id);
        self::assertSame('trash', $product->status);
    }

    public function testCreatesCoreDigitalWooCommerceDraft(): void
    {
        $calls = [];
        $gateway = new WordPressWooCommerceDraftGateway(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = [$name, $arguments];

                return match ($name) {
                    'get_gmt_from_date' => '2025-04-20 09:00:00',
                    'wp_insert_post' => 5028,
                    'is_wp_error' => false,
                    default => true,
                };
            }
        );

        $productId = $gateway->createCore(
            $this->data(
                downloadUrl: 'https://wp-shop.org/file.zip',
                featuredImageId: 77
            )
        );

        self::assertSame(5028, $productId);

        $insert = $this->firstCall(
            $calls,
            'wp_insert_post'
        );
        self::assertSame(
            'Aabbe – Digital Marketplace WordPress Theme 6.2.0',
            $insert[0]['post_title']
        );
        self::assertSame('draft', $insert[0]['post_status']);
        self::assertSame('aabbe', $insert[0]['post_name']);
        self::assertSame(
            '2025-04-20 12:00:00',
            $insert[0]['post_date']
        );

        $meta = $this->metaCalls($calls);
        self::assertSame('249', $meta['_regular_price']);
        self::assertSame('249', $meta['_price']);
        self::assertSame('yes', $meta['_virtual']);
        self::assertSame('yes', $meta['_downloadable']);
        self::assertSame('-1', $meta['_download_limit']);
        self::assertSame('10', $meta['_download_expiry']);
        self::assertArrayHasKey(
            '_downloadable_files',
            $meta
        );

        self::assertNotSame(
            [],
            $this->callsNamed(
                $calls,
                'set_post_thumbnail'
            )
        );
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     * @return array<int, mixed>
     */
    private function firstCall(
        array $calls,
        string $name
    ): array {
        foreach ($calls as $call) {
            if ($call[0] === $name) {
                return $call[1];
            }
        }

        self::fail('Call not found: ' . $name);
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     * @return list<array<int, mixed>>
     */
    private function callsNamed(
        array $calls,
        string $name
    ): array {
        $result = [];

        foreach ($calls as $call) {
            if ($call[0] === $name) {
                $result[] = $call[1];
            }
        }

        return $result;
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     * @return array<string, mixed>
     */
    private function metaCalls(array $calls): array
    {
        $meta = [];

        foreach (
            $this->callsNamed(
                $calls,
                'update_post_meta'
            ) as $arguments
        ) {
            $meta[(string) $arguments[1]] = $arguments[2];
        }

        return $meta;
    }

    private function data(
        string $downloadUrl = '',
        int $featuredImageId = 0
    ): ProductDraftData {
        return new ProductDraftData(
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            26350912,
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            '249',
            'https://themeforest.net/item/aabbe/26350912',
            'themeforest-26350912-aabbe-6.2.0.zip',
            $downloadUrl,
            $featuredImageId,
            [],
            'RU short',
            'RU long',
            'RU meta',
            'EN short',
            'EN long',
            'EN meta',
            'Pre-activated.',
            false,
            false
        );
    }
}
