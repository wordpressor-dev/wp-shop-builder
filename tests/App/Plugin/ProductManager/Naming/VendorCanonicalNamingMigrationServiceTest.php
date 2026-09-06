<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Naming;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Naming\TranslatePressTitleInspector;
use WPShop\App\Plugin\ProductManager\Naming\VendorCanonicalNamingMigrationService;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus;

final class VendorCanonicalNamingMigrationServiceTest extends TestCase
{
    public function testUpdatesApprovedTitleAndRequiresExactTranslatePressTitle(): void
    {
        $old = 'Yoast SEO Premium – Advanced SEO With Real-Time Guidance and Built-In AI';
        $new = 'Yoast SEO Premium';
        $post = (object) [
            'ID' => 2681,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => $old,
            'post_name' => 'yoast-seo-premium',
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
                    'sales_page' => 'https://yoast.com/product/yoast-seo-premium-wordpress/',
                    '_wp_shop_source_item_id' => 0,
                    '_sku' => 'yoast-seo-premium.zip',
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

                return 2681;
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
                'translated' => $old,
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
            ->with('yoast-seo-premium')
            ->willReturn('EN_HTTP_200');

        $service = new VendorCanonicalNamingMigrationService(
            new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            ),
            new TranslatePressTitleInspector($database),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $service->apply(2681);

        self::assertSame('UPDATED', $result['status']);
        self::assertSame($new, $result['newTitle']);
        self::assertSame('TRP_EXACT', $result['translation']);
        self::assertSame($new, $post->post_title);
        self::assertSame('yoast-seo-premium', $post->post_name);
        self::assertCount(1, $writes);
        self::assertSame($new, $writes[0]['post_title']);
        self::assertSame('yoast-seo-premium', $writes[0]['post_name']);
    }

    public function testRollsBackTitleWhenTranslatePressIsNotExact(): void
    {
        $old = 'Yoast SEO Premium – Advanced SEO With Real-Time Guidance and Built-In AI';
        $post = (object) [
            'ID' => 2681,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => $old,
            'post_name' => 'yoast-seo-premium',
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
                    'sales_page' => 'https://yoast.com/product/yoast-seo-premium-wordpress/',
                    '_wp_shop_source_item_id' => 0,
                    '_sku' => 'yoast-seo-premium.zip',
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

                return 2681;
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
                'translated' => $old,
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
                0,
                1,
                0,
                0,
                []
            )
        );
        $registrar = $this->createMock(
            TranslationRegistrarInterface::class
        );
        $registrar->method('registerPage')
            ->willReturn('EN_HTTP_200');

        $service = new VendorCanonicalNamingMigrationService(
            new VendorProductNamingAuditService(
                $call(...),
                new ProductArchiveIdentityInspector()
            ),
            new TranslatePressTitleInspector($database),
            $dictionary,
            $registrar,
            $call(...)
        );

        $result = $service->apply(2681);

        self::assertSame('ERROR', $result['status']);
        self::assertSame('TRP_ROLLBACK_OK', $result['translation']);
        self::assertSame($old, $post->post_title);
        self::assertCount(2, $writes);
        self::assertSame('Yoast SEO Premium', $writes[0]['post_title']);
        self::assertSame($old, $writes[1]['post_title']);
    }
}
