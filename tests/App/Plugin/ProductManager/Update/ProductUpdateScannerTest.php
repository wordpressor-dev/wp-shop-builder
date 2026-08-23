<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanner;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;

final class ProductUpdateScannerTest extends TestCase
{
    public function testClassifiesSameUpdateAndStaleRowsWithoutWriting(): void
    {
        $products = [
            10 => $this->product('Theme A 1.0.0', '1.0.0', 101, 'theme-a'),
            20 => $this->product('Theme B 1.0.0', '1.0.0', 202, 'theme-b'),
            30 => $this->product('Theme C 2.0.0', '2.0.0', 303, 'theme-c'),
        ];
        $writes = [];
        $call = function (
            string $function,
            mixed ...$args
        ) use (
            $products,
            &$writes
        ): mixed {
            if ($function === 'get_posts') {
                return [10, 20, 30];
            }

            $id = isset($args[0]) && is_int($args[0])
                ? $args[0]
                : (isset($args[1]) && is_int($args[1]) ? $args[1] : 0);

            if ($function === 'get_post_type') {
                return isset($products[$id]) ? 'product' : null;
            }

            if ($function === 'get_post_status') {
                return 'publish';
            }

            if ($function === 'get_post_field') {
                $field = (string) ($args[0] ?? '');
                $id = (int) ($args[1] ?? 0);

                return $field === 'post_title'
                    ? $products[$id]['title']
                    : '2026-01-01 12:00:00';
            }

            if ($function === 'get_post_meta') {
                $id = (int) ($args[0] ?? 0);
                $key = (string) ($args[1] ?? '');

                return match ($key) {
                    'attr_version_value' => $products[$id]['version'],
                    'sales_page' => $products[$id]['sales_page'],
                    '_wp_shop_source_item_id' => $products[$id]['item_id'],
                    '_wp_shop_source_update_date' => '2026-01-01',
                    '_sku' => $products[$id]['sku'],
                    '_downloadable_files' => [
                        'x' => [
                            'name' => $products[$id]['sku'],
                            'file' => $products[$id]['download_url'],
                        ],
                    ],
                    default => '',
                };
            }

            if (
                str_starts_with($function, 'update_')
                || $function === 'wp_update_post'
            ) {
                $writes[] = $function;
            }

            return null;
        };

        $envato = $this->createMock(EnvatoClientInterface::class);
        $envato->method('fetch')->willReturnCallback(
            static function (string $url): EnvatoItem {
                if (str_contains($url, '/101')) {
                    return self::item(101, 'theme-a', '1.0.0');
                }

                if (str_contains($url, '/202')) {
                    return self::item(202, 'theme-b', '1.1.0');
                }

                return self::item(303, 'theme-c', '1.5.0');
            }
        );

        $updater = new ProductVersionUpdater($call(...));
        $advisor = new ProductUpdateEnvatoAdvisor($envato);
        $scanner = new ProductUpdateScanner(
            $updater,
            $advisor,
            $call(...)
        );

        $rows = $scanner->scan(0, 10, 'token');

        self::assertCount(3, $rows);
        self::assertSame('SAME', $rows[0]->status);
        self::assertSame('UPDATE_AVAILABLE', $rows[1]->status);
        self::assertSame('MANUAL_REVIEW', $rows[2]->status);
        self::assertStringContainsString(
            'downgrade suggestion blocked',
            $rows[2]->message
        );
        self::assertSame([], $writes);
    }

    /**
     * @return array<string, mixed>
     */
    private function product(
        string $title,
        string $version,
        int $itemId,
        string $slug
    ): array {
        $sku = sprintf(
            'themeforest-%d-%s-%s.zip',
            $itemId,
            $slug,
            $version
        );

        return [
            'title' => $title,
            'version' => $version,
            'item_id' => $itemId,
            'sales_page' => sprintf(
                'https://themeforest.net/item/%s/%d',
                $slug,
                $itemId
            ),
            'sku' => $sku,
            'download_url' => sprintf(
                'https://wp-shop.org/uploads/%d/%s',
                $itemId,
                $sku
            ),
        ];
    }

    private static function item(
        int $itemId,
        string $slug,
        string $version
    ): EnvatoItem {
        return new EnvatoItem(
            $itemId,
            'Theme',
            $slug,
            $version,
            '2026-08-23',
            'Vendor',
            sprintf(
                'https://themeforest.net/item/%s/%d',
                $slug,
                $itemId
            ),
            0,
            null,
            [],
            sprintf(
                'themeforest-%d-%s-%s.zip',
                $itemId,
                $slug,
                $version
            ),
            []
        );
    }
}
