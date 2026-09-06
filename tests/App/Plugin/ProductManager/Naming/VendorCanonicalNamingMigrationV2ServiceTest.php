<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Naming\TranslatePressTitleInspector;
use WPShop\App\Plugin\ProductManager\Naming\VendorCanonicalNamingMigrationV2Service;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus;

final class VendorCanonicalNamingMigrationV2ServiceTest extends TestCase
{
    public function testAcceptsOnlyTheApprovedMalformedEnglishSnapshot(): void
    {
        $old = 'JetWooBuilder – WordPress Plugin for Shop Page, '
            . 'Product, Cart & Checkout for WooCommerce';
        $badEnglish = 'is a WooCommerce page builder for Elementor. '
            . 'Visually design custom shop, single product, cart, and checkout '
            . 'pages with pre-made templates and widgets.';
        $post = (object) [
            'ID' => 3496,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => $old,
            'post_name' => 'jetwoobuilder',
        ];
        $writes = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$writes
        ): mixed {
            if ($name === 'get_post') {
                return clone $post;
            }

            if ($name === 'get_post_field') {
                return $post->post_title;
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return match ($key) {
                    '_wp_shop_source_type' => 'vendor',
                    'sales_page' => 'https://crocoblock.com/plugins/jetwoobuilder/',
                    '_wp_shop_source_item_id' => 0,
                    '_sku' => 'jetwoobuilder.zip',
                    '_wp_shop_product_type' => 'plugin',
                    '_downloadable_files' => [],
                    default => '',
                };
            }

            if ($name === 'wp_update_post') {
                $data = (array) ($arguments[0] ?? []);
                $writes[] = $data;
                $post->post_title = (string) ($data['post_title'] ?? '');
                $post->post_name = (string) ($data['post_name'] ?? '');

                return 3496;
            }

            return null;
        };

        $database = $this->createMock(
            DatabaseConnectionInterface::class
        );
        $database->method('fetchOne')->willReturn([
            'table' => 'wp_trp_dictionary_ru_ru_en_us',
        ]);
        $database->method('fetchAll')->willReturn([
            [
                'original' => $old,
                'translated' => $badEnglish,
                'status' => 2,
            ],
        ]);

        $dictionary = $this->createMock(
            TranslationDictionaryInterface::class
        );
        $dictionary->method('status')->willReturn(
            new TranslationDictionaryStatus(
                true,
                1,
                1,
                0,
                0,
                0,
                []
            )
        );
        $registrar = $this->createMock(
            TranslationRegistrarInterface::class
        );
        $registrar->expects(self::once())
            ->method('registerPage')
            ->with('jetwoobuilder')
            ->willReturn('EN_HTTP_200');

        $service = new VendorCanonicalNamingMigrationV2Service(
            new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            ),
            new TranslatePressTitleInspector($database),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $service->apply(3496);

        self::assertSame('UPDATED', $result['status']);
        self::assertSame('JetWooBuilder', $result['newTitle']);
        self::assertSame('TRP_EXACT', $result['translation']);
        self::assertSame('JetWooBuilder', $post->post_title);
        self::assertSame('jetwoobuilder', $post->post_name);
        self::assertCount(1, $writes);
    }

    public function testStopsWhenMalformedEnglishSnapshotHasChanged(): void
    {
        $old = 'JetWooBuilder – WordPress Plugin for Shop Page, Product, Cart & Checkout for WooCommerce';
        $post = (object) [
            'ID' => 3496,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => $old,
            'post_name' => 'jetwoobuilder',
        ];
        $writes = [];

        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$writes
        ): mixed {
            if ($name === 'get_post') {
                return clone $post;
            }

            if ($name === 'get_post_field') {
                return $post->post_title;
            }

            if ($name === 'get_post_meta') {
                $key = (string) ($arguments[1] ?? '');

                return match ($key) {
                    '_wp_shop_source_type' => 'vendor',
                    'sales_page' => 'https://crocoblock.com/plugins/jetwoobuilder/',
                    '_wp_shop_source_item_id' => 0,
                    '_sku' => 'jetwoobuilder.zip',
                    '_wp_shop_product_type' => 'plugin',
                    '_downloadable_files' => [],
                    default => '',
                };
            }

            if ($name === 'wp_update_post') {
                $writes[] = (array) ($arguments[0] ?? []);

                return 3496;
            }

            return null;
        };

        $database = $this->createMock(
            DatabaseConnectionInterface::class
        );
        $database->method('fetchOne')->willReturn([
            'table' => 'wp_trp_dictionary_ru_ru_en_us',
        ]);
        $database->method('fetchAll')->willReturn([
            [
                'original' => $old,
                'translated' => 'Unexpected edited EN title',
                'status' => 2,
            ],
        ]);

        $dictionary = $this->createMock(
            TranslationDictionaryInterface::class
        );
        $registrar = $this->createMock(
            TranslationRegistrarInterface::class
        );

        $service = new VendorCanonicalNamingMigrationV2Service(
            new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            ),
            new TranslatePressTitleInspector($database),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $service->apply(3496);

        self::assertSame('SKIP', $result['status']);
        self::assertSame('TRANSLATED', $result['translation']);
        self::assertSame([], $writes);
        self::assertSame($old, $post->post_title);
    }
}
