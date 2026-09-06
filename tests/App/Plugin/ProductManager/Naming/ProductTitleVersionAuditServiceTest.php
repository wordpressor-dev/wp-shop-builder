<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionAuditService;

final class ProductTitleVersionAuditServiceTest extends TestCase
{
    public function testFindsOnlyExactStoredVersionSuffixAcrossAllSources(): void
    {
        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$writes): mixed {
            if ($name === 'get_posts') {
                return [10, 20, 30, 40, 50];
            }

            if ($name === 'get_post_field') {
                return match ((int) ($arguments[1] ?? 0)) {
                    10 => 'Elementor Pro 3.32.1',
                    20 => 'Eduma 5.7.2',
                    30 => 'WPBakery Page Builder for WordPress 8.7',
                    40 => 'Already Clean Product',
                    50 => 'No Version Product',
                    default => '',
                };
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                $meta = [
                    10 => [
                        'attr_version_value' => '3.32.1',
                        'sales_page' => 'https://elementor.com/pro/',
                    ],
                    20 => [
                        'attr_version_value' => '5.7.2',
                        'sales_page' => 'https://themeforest.net/item/eduma/14058034',
                    ],
                    30 => [
                        'attr_version_value' => '8.7',
                        'sales_page' => 'https://codecanyon.net/item/wpbakery/242431',
                    ],
                    40 => [
                        'attr_version_value' => '1.2.3',
                        'sales_page' => 'https://vendor.example/product/',
                    ],
                    50 => [
                        'attr_version_value' => '—',
                        'sales_page' => 'https://elements.envato.com/example',
                    ],
                ];

                return $meta[$productId][$key] ?? '';
            }

            if (
                str_starts_with($name, 'update_')
                || $name === 'wp_update_post'
                || $name === 'delete_post_meta'
            ) {
                $writes[] = $name;
            }

            return null;
        };

        $audit = new ProductTitleVersionAuditService(
            $call(...)
        );

        self::assertSame(5, $audit->candidateCount());
        $rows = $audit->scan(0, 20);

        self::assertCount(5, $rows);

        self::assertSame('REMOVE_VERSION', $rows[0]->action);
        self::assertSame('Elementor Pro', $rows[0]->recommendedTitle);
        self::assertSame('VENDOR', $rows[0]->source);

        self::assertSame('REMOVE_VERSION', $rows[1]->action);
        self::assertSame('Eduma', $rows[1]->recommendedTitle);
        self::assertSame('THEMEFOREST', $rows[1]->source);

        self::assertSame('REMOVE_VERSION', $rows[2]->action);
        self::assertSame(
            'WPBakery Page Builder for WordPress',
            $rows[2]->recommendedTitle
        );
        self::assertSame('CODECANYON', $rows[2]->source);

        self::assertSame('KEEP', $rows[3]->action);
        self::assertSame('Already Clean Product', $rows[3]->recommendedTitle);

        self::assertSame('KEEP', $rows[4]->action);
        self::assertSame('ENVATO', $rows[4]->source);

        self::assertSame([], $writes);
    }

    public function testSupportsLeadingVBeforeExactStoredVersion(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [60];
            }

            if ($name === 'get_post_field') {
                return 'Example Product v2.1.0';
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return $key === 'attr_version_value'
                    ? '2.1.0'
                    : 'https://vendor.example/';
            }

            return null;
        };

        $rows = (new ProductTitleVersionAuditService(
            $call(...)
        ))->scan(0, 10);

        self::assertCount(1, $rows);
        self::assertSame('REMOVE_VERSION', $rows[0]->action);
        self::assertSame('Example Product', $rows[0]->recommendedTitle);
    }
}
