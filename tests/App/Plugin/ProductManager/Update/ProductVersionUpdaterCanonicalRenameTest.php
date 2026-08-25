<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;

final class ProductVersionUpdaterCanonicalRenameTest extends TestCase
{
    private const OLD_SKU = 'themeforest-14058034-eduma-education-wordpress-theme-5.9.4.zip';
    private const NEW_SKU = 'themeforest-14058034-education-wordpress-theme-education-wp-5.9.4.zip';

    public function testPreflightAllowsCanonicalRenameForSameThemeForestItemId(): void
    {
        $calls = [];
        $updater = new ProductVersionUpdater(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = [$name, $arguments];

                return match ($name) {
                    'get_post_type' => 'product',
                    'get_post_status' => 'publish',
                    'get_post_meta' => self::identityMeta($arguments),
                    'wc_get_product_id_by_sku' => 5024,
                    default => null,
                };
            }
        );

        $result = $updater->preflight($this->data());

        self::assertTrue($result->success);
        self::assertContains(
            'SKU AUTO-SYNC: ' . self::OLD_SKU . ' -> ' . self::NEW_SKU,
            $result->logs
        );
        self::assertContains('NO PRODUCT WRITTEN = YES', $result->logs);
        self::assertSame([], $this->callsNamed($calls, 'wp_update_post'));
        self::assertSame([], $this->callsNamed($calls, 'update_post_meta'));
    }

    public function testApplyWritesCanonicalSkuAndDoesNotChangeProductSlug(): void
    {
        $calls = [];
        $updater = new ProductVersionUpdater(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = [$name, $arguments];

                return match ($name) {
                    'get_post_type' => 'product',
                    'get_post_status' => 'publish',
                    'get_post_meta' => self::identityMeta($arguments),
                    'wc_get_product_id_by_sku' => 5024,
                    'get_gmt_from_date' => '2026-08-12 09:00:00',
                    'wp_update_post' => 5024,
                    'is_wp_error' => false,
                    'get_current_user_id' => 0,
                    default => true,
                };
            }
        );

        $result = $updater->update($this->data());

        self::assertTrue($result->success);

        $postUpdate = $this->firstCall($calls, 'wp_update_post');
        self::assertArrayNotHasKey('post_name', $postUpdate[0]);

        $meta = $this->metaCalls($calls);
        self::assertSame(self::NEW_SKU, $meta['_sku']);
        self::assertSame(
            self::NEW_SKU,
            reset($meta['_downloadable_files'])['name']
        );
        self::assertSame('5.9.4', $meta['attr_version_value']);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private static function identityMeta(array $arguments): mixed
    {
        return match ($arguments[1] ?? '') {
            'attr_version_value' => '5.9.4',
            '_sku' => self::OLD_SKU,
            default => '',
        };
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     * @return list<array<int, mixed>>
     */
    private function callsNamed(array $calls, string $name): array
    {
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
     * @return array<int, mixed>
     */
    private function firstCall(array $calls, string $name): array
    {
        foreach ($calls as $call) {
            if ($call[0] === $name) {
                return $call[1];
            }
        }

        self::fail('Call not found: ' . $name);
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     * @return array<string, mixed>
     */
    private function metaCalls(array $calls): array
    {
        $meta = [];

        foreach ($this->callsNamed($calls, 'update_post_meta') as $arguments) {
            $meta[(string) $arguments[1]] = $arguments[2];
        }

        return $meta;
    }

    private function data(): ProductUpdateData
    {
        return new ProductUpdateData(
            5024,
            'Eduma – Education WordPress Theme',
            14058034,
            '5.9.4',
            '5.9.4',
            '2026-08-12',
            'https://themeforest.net/item/education-wordpress-theme-education-wp/14058034',
            self::OLD_SKU,
            self::OLD_SKU,
            'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/THEMES/'
                . 'Themeforest/14058034/' . self::NEW_SKU
        );
    }
}
