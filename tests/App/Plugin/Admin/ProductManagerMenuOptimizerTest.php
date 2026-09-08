<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\ProductManagerMenuOptimizer;

final class ProductManagerMenuOptimizerTest extends TestCase
{
    public function testHidesLegacySidebarEntriesButKeepsCoreWorkflow(): void
    {
        $removed = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$removed): mixed {
            if ($name === 'remove_submenu_page') {
                $removed[] = [
                    (string) ($arguments[0] ?? ''),
                    (string) ($arguments[1] ?? ''),
                ];
            }

            return null;
        };

        $optimizer = new ProductManagerMenuOptimizer($call(...));
        $optimizer->optimize();

        $slugs = array_map(
            static fn (array $row): string => $row[1],
            $removed
        );

        self::assertContains('wp-shop-builder', $slugs);
        self::assertContains(
            'wp-shop-builder-vendor-naming-audit',
            $slugs
        );
        self::assertContains(
            'wp-shop-builder-vendor-cover-generator',
            $slugs
        );
        self::assertContains(
            'wp-shop-builder-product-update',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-product-batch-intake',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-product-update-queue',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-product-update-full-scan',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-product-manager',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-product-editorial-migration',
            $slugs
        );
        self::assertNotContains(
            'wp-shop-builder-en-content-audit',
            $slugs
        );
    }
}
