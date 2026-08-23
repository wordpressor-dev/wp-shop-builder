<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Write;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;

final class ProductMetadataWriterTest extends TestCase
{
    public function testWritesDisplayFieldsAcfReferencesAndSafeSourceMeta(): void
    {
        $meta = [];
        $writer = new ProductMetadataWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$meta): mixed {
                if ($name === 'update_post_meta') {
                    $meta[(string) $arguments[1]] = $arguments[2];

                    return true;
                }

                return null;
            }
        );

        $logs = $writer->write(5028, $this->data());

        self::assertSame(
            '<strong>Версия:</strong>',
            $meta['Attr_version']
        );
        self::assertSame(
            'field_68d522389bacc',
            $meta['_Attr_version']
        );
        self::assertSame('6.2.0', $meta['attr_version_value']);
        self::assertSame(
            'field_68d531d09ce86',
            $meta['_attr_version_value']
        );
        self::assertSame('Темы', $meta['attr_category_value']);
        self::assertSame(
            'Themeforest',
            $meta['attr_brand_value']
        );
        self::assertSame(
            'QuomodoTheme',
            $meta['attr_developer_value']
        );
        self::assertSame(
            'https://themeforest.net/item/aabbe/26350912',
            $meta['sales_page']
        );
        self::assertSame('Pre-activated.', $meta['Notes']);
        self::assertSame(
            '2025-04-20',
            $meta['_wp_shop_source_update_date']
        );
        self::assertSame(
            '26350912',
            $meta['_wp_shop_source_item_id']
        );
        self::assertSame(
            'EN short',
            $meta['_wp_shop_en_short_description']
        );
        self::assertArrayNotHasKey(
            'attr_update_value',
            $meta
        );
        self::assertContains(
            'attr_update_value = SKIPPED',
            $logs
        );
    }

    private function data(): ProductDraftData
    {
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
            '',
            0,
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
