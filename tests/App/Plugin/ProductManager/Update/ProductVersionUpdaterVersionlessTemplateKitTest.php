<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;

final class ProductVersionUpdaterVersionlessTemplateKitTest extends TestCase
{
    public function testLoadTreatsDisplayDashAsEmptyVersion(): void
    {
        $updater = new ProductVersionUpdater(
            $this->call()
        );

        $snapshot = $updater->load(5156);

        self::assertSame('', $snapshot->version);
        self::assertSame(
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            $snapshot->baseTitle
        );
        self::assertSame(
            $this->downloadUrl(),
            $snapshot->downloadUrl
        );
    }

    public function testPreflightAcceptsStoredDashAsVersionlessState(): void
    {
        $updater = new ProductVersionUpdater(
            $this->call()
        );

        $result = $updater->preflight($this->data());

        self::assertTrue($result->success);
        self::assertContains(
            'CURRENT VERSION = [empty]',
            $result->logs
        );
        self::assertContains(
            'NEW VERSION = NOT PUBLISHED; TEMPLATE KIT DATE/FILE MODE',
            $result->logs
        );
        self::assertContains(
            'SKU / VERSION = MATCH',
            $result->logs
        );
    }

    public function testUpdateKeepsDashOnlyAsDisplayPlaceholder(): void
    {
        $meta = [];
        $updater = new ProductVersionUpdater(
            $this->call($meta)
        );

        $result = $updater->update($this->data());

        self::assertTrue($result->success);
        self::assertSame('—', $meta['attr_version_value']);
        self::assertSame($this->sku(), $meta['_sku']);
        self::assertSame(
            $this->downloadUrl(),
            reset($meta['_downloadable_files'])['file']
        );
        self::assertContains(
            'VERSION = [not published]',
            $result->logs
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function call(array &$meta = []): \Closure
    {
        return function (
            string $name,
            mixed ...$arguments
        ) use (&$meta): mixed {
            if ($name === 'get_post_meta') {
                return match ((string) ($arguments[1] ?? '')) {
                    'attr_version_value' => '—',
                    'sales_page' => $this->salesPage(),
                    '_wp_shop_source_item_id' => '43194184',
                    '_wp_shop_source_update_date' => '2025-12-09',
                    '_sku' => $this->sku(),
                    '_downloadable_files' => [
                        'existing' => [
                            'name' => $this->sku(),
                            'file' => $this->downloadUrl(),
                        ],
                    ],
                    default => '',
                };
            }

            if ($name === 'update_post_meta') {
                $meta[(string) $arguments[1]] = $arguments[2];

                return true;
            }

            return match ($name) {
                'get_post_type' => 'product',
                'get_post_status' => 'publish',
                'get_post_field' => $arguments[0] === 'post_title'
                    ? 'EstateRoof – Roofing Services Elementor Pro Template Kit'
                    : '2025-12-09 12:00:00',
                'wc_get_product_id_by_sku' => 5156,
                'get_gmt_from_date' => '2025-12-09 09:00:00',
                'wp_update_post' => 5156,
                'is_wp_error' => false,
                'get_current_user_id' => 0,
                default => null,
            };
        };
    }

    private function data(): ProductUpdateData
    {
        return new ProductUpdateData(
            5156,
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            43194184,
            '',
            '',
            '2025-12-09',
            $this->salesPage(),
            $this->sku(),
            $this->sku(),
            $this->downloadUrl()
        );
    }

    private function salesPage(): string
    {
        return 'https://themeforest.net/item/'
            . 'estateroof-roofing-services-elementor-pro-template-kit/43194184';
    }

    private function sku(): string
    {
        return 'themeforest-43194184-estateroof-roofing-services-'
            . 'elementor-pro-template-kit.zip';
    }

    private function downloadUrl(): string
    {
        return 'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/'
            . 'TEMPLATES/43194184/' . $this->sku();
    }
}
