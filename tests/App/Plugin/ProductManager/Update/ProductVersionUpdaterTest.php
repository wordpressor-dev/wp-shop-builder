<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;

final class ProductVersionUpdaterTest extends TestCase
{
    public function testPreflightIsNonWritingAndBuildsCanonicalSku(): void
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
                    'get_post_meta' => self::currentIdentityMeta($arguments),
                    'wc_get_product_id_by_sku' => 5034,
                    default => null,
                };
            }
        );

        $result = $updater->preflight($this->data());

        self::assertTrue($result->success);
        self::assertContains(
            'NO PRODUCT WRITTEN = YES',
            $result->logs
        );
        self::assertContains(
            'SKU AUTO-SYNC: '
                . 'themeforest-22380037-veera-multipurpose-woocommerce-theme-1.9.0.zip'
                . ' -> '
                . 'themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            $result->logs
        );
        self::assertSame(
            [],
            $this->callsNamed($calls, 'wp_update_post')
        );
        self::assertSame(
            [],
            $this->callsNamed($calls, 'update_post_meta')
        );
    }

    public function testUpdatePreservesPublicationDateAndStatus(): void
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
                    'get_post_meta' => self::currentIdentityMeta($arguments),
                    'wc_get_product_id_by_sku' => 5034,
                    'wp_update_post' => 5034,
                    'is_wp_error' => false,
                    default => true,
                };
            }
        );

        $result = $updater->update($this->data());

        self::assertTrue($result->success);
        self::assertContains(
            'RU/EN CONTENT = PRESERVED',
            $result->logs
        );
        self::assertContains(
            'TAGS / ATTRIBUTES / LABELS = PRESERVED',
            $result->logs
        );

        $update = $this->firstCall($calls, 'wp_update_post');
        self::assertSame(5034, $update[0]['ID']);
        self::assertSame(
            'Veera – Multipurpose WooCommerce Theme 2.0.0',
            $update[0]['post_title']
        );
        self::assertArrayNotHasKey('post_date', $update[0]);
        self::assertArrayNotHasKey('post_date_gmt', $update[0]);
        self::assertArrayNotHasKey('post_status', $update[0]);
        self::assertContains(
            'PUBLICATION DATE / STATUS = PRESERVED',
            $result->logs
        );

        $meta = $this->metaCalls($calls);
        self::assertSame(
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            $meta['_sku']
        );
        self::assertSame('2.0.0', $meta['attr_version_value']);
        self::assertSame(
            '2026-08-20',
            $meta['_wp_shop_source_update_date']
        );
        self::assertArrayHasKey('_downloadable_files', $meta);
        self::assertArrayNotHasKey('post_content', $meta);
        self::assertArrayNotHasKey('br_labels', $meta);
    }

    public function testVendorUpdateDoesNotRequireEnvatoItemId(): void
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
                    'get_post_meta' => match (
                        (string) ($arguments[1] ?? '')
                    ) {
                        'attr_version_value' => '4.2.3',
                        '_sku' => 'elementor-pro-4.2.3-package.zip',
                        default => '',
                    },
                    'wc_get_product_id_by_sku' => 4066,
                    'wp_update_post' => 4066,
                    'is_wp_error' => false,
                    default => true,
                };
            }
        );
        $data = new ProductUpdateData(
            4066,
            'Elementor Pro Website Builder',
            0,
            '4.2.3',
            '4.2.4',
            '2026-09-05',
            'https://elementor.com/pro/',
            'elementor-pro-4.2.3-package.zip',
            'elementor-pro-4.2.4-package.zip',
            'https://wp-shop.org/wp-content/uploads/'
                . 'woocommerce_uploads/PLUGINS/Elementor/'
                . 'elementor-pro-4.2.4-package.zip',
            'vendor'
        );

        $result = $updater->update($data);

        self::assertTrue($result->success);
        self::assertContains('SOURCE TYPE = VENDOR', $result->logs);
        self::assertContains(
            'SKU AUTO-SYNC: elementor-pro-4.2.3-package.zip'
                . ' -> elementor-pro-4.2.4-package.zip',
            $result->logs
        );

        $meta = $this->metaCalls($calls);
        self::assertSame('vendor', $meta['_wp_shop_source_type']);
        self::assertSame('4.2.4', $meta['attr_version_value']);
        self::assertArrayNotHasKey('_wp_shop_source_item_id', $meta);
    }

    public function testSuccessfulUpdateMarksMatchingScannerReportDone(): void
    {
        $calls = [];
        $report = [
            'seen' => [5034 => 'UPDATE_AVAILABLE'],
            'attention' => [
                5034 => [
                    'productId' => 5034,
                    'title' => 'Veera – Multipurpose WooCommerce Theme 1.9.0',
                    'currentVersion' => '1.9.0',
                    'envatoVersion' => '2.0.0',
                    'envatoUpdateDate' => '2026-08-20',
                    'status' => 'UPDATE_AVAILABLE',
                    'message' => 'Newer Envato version found.',
                ],
            ],
            'errors' => [],
            'started_at' => '2026-08-24 12:50:48',
            'updated_at' => '2026-08-24 12:50:48',
        ];

        $updater = new ProductVersionUpdater(
            static function (
                string $name,
                mixed ...$arguments
            ) use (
                &$calls,
                &$report
            ): mixed {
                $calls[] = [$name, $arguments];

                return match ($name) {
                    'get_post_type' => 'product',
                    'get_post_status' => 'publish',
                    'get_post_meta' => self::currentIdentityMeta($arguments),
                    'wc_get_product_id_by_sku' => 5034,
                    'get_gmt_from_date' => '2026-08-20 09:00:00',
                    'wp_update_post' => 5034,
                    'is_wp_error' => false,
                    'get_current_user_id' => 42,
                    'get_user_meta' => $report,
                    'current_time' => '2026-08-24 13:20:00',
                    'update_user_meta' => self::replaceReport(
                        $report,
                        $arguments
                    ),
                    default => true,
                };
            }
        );

        $result = $updater->update($this->data());

        self::assertTrue($result->success);
        self::assertContains(
            'UPDATE SCANNER REPORT = DONE',
            $result->logs
        );
        self::assertArrayNotHasKey(5034, $report['attention']);
        self::assertSame('DONE', $report['seen'][5034]);
        self::assertSame('2026-08-24 13:20:00', $report['updated_at']);
    }

    public function testStaleCurrentVersionStopsBeforeAnyWrite(): void
    {
        $calls = [];
        $updater = new ProductVersionUpdater(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$calls): mixed {
                $calls[] = [$name, $arguments];

                if ($name === 'get_post_type') {
                    return 'product';
                }

                if ($name === 'get_post_status') {
                    return 'publish';
                }

                if ($name === 'get_post_meta') {
                    return $arguments[1] === 'attr_version_value'
                        ? '2.0.0'
                        : self::currentIdentityMeta($arguments);
                }

                if ($name === 'wc_get_product_id_by_sku') {
                    return 5034;
                }

                return null;
            }
        );

        $result = $updater->update($this->data());

        self::assertFalse($result->success);
        self::assertContains(
            'STOP: PRODUCT NOT UPDATED.',
            $result->logs
        );
        self::assertContains(
            'STALE FORM: Current Version changed from 1.9.0 to 2.0.0. Reload product before continuing.',
            $result->logs
        );
        self::assertSame([], $this->callsNamed($calls, 'wp_update_post'));
        self::assertSame([], $this->callsNamed($calls, 'update_post_meta'));
    }

    public function testLoadFallsBackToSalesPageItemIdAndPostDate(): void
    {
        $updater = new ProductVersionUpdater(
            static function (
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
                    return $arguments[0] === 'post_title'
                        ? 'Veera – Multipurpose WooCommerce Theme 1.9.0'
                        : '2026-05-13 12:00:00';
                }

                if ($name === 'get_post_meta') {
                    return match ($arguments[1]) {
                        'attr_version_value' => '1.9.0',
                        'sales_page' => 'https://themeforest.net/item/'
                            . 'veera-multipurpose-woocommerce-theme/22380037',
                        '_wp_shop_source_item_id' => '',
                        '_wp_shop_source_update_date' => '',
                        '_sku' => 'themeforest-22380037-veera-'
                            . 'multipurpose-woocommerce-theme-1.9.0.zip',
                        '_downloadable_files' => [
                            'x' => [
                                'name' => 'old.zip',
                                'file' => 'https://wp-shop.org/old.zip',
                            ],
                        ],
                        default => '',
                    };
                }

                return null;
            }
        );

        $snapshot = $updater->load(5034);

        self::assertSame(22380037, $snapshot->itemId);
        self::assertSame('2026-05-13', $snapshot->sourceUpdateDate);
        self::assertSame(
            'Veera – Multipurpose WooCommerce Theme',
            $snapshot->baseTitle
        );
        self::assertSame(
            'https://wp-shop.org/old.zip',
            $snapshot->downloadUrl
        );
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private static function currentIdentityMeta(array $arguments): mixed
    {
        return match ($arguments[1] ?? '') {
            'attr_version_value' => '1.9.0',
            '_sku' => 'themeforest-22380037-veera-'
                . 'multipurpose-woocommerce-theme-1.9.0.zip',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $report
     * @param array<int, mixed> $arguments
     */
    private static function replaceReport(
        array &$report,
        array $arguments
    ): bool {
        $stored = $arguments[2] ?? null;

        if (is_array($stored)) {
            $report = $stored;
        }

        return true;
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
     * @return array<string, mixed>
     */
    private function metaCalls(array $calls): array
    {
        $meta = [];

        foreach (
            $this->callsNamed($calls, 'update_post_meta') as $arguments
        ) {
            $meta[(string) $arguments[1]] = $arguments[2];
        }

        return $meta;
    }

    private function data(): ProductUpdateData
    {
        $salesPage = 'https://themeforest.net/item/'
            . 'veera-multipurpose-woocommerce-theme/22380037';
        $currentSku = 'themeforest-22380037-veera-'
            . 'multipurpose-woocommerce-theme-1.9.0.zip';
        $downloadUrl = 'https://wp-shop.org/wp-content/uploads/'
            . 'woocommerce_uploads/THEMES/Themeforest/22380037/'
            . 'themeforest-22380037-veera-multipurpose-'
            . 'woocommerce-theme-2.0.0.zip';

        return new ProductUpdateData(
            5034,
            'Veera – Multipurpose WooCommerce Theme',
            22380037,
            '1.9.0',
            '2.0.0',
            '2026-08-20',
            $salesPage,
            $currentSku,
            $currentSku,
            $downloadUrl
        );
    }
}
