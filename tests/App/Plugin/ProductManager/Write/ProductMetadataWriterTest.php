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
        $writer = $this->writer($meta);

        $logs = $writer->write(5028, $this->themeData());

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
        self::assertSame('theme', $meta['_wp_shop_product_type']);
        self::assertSame('THEMES', $meta['_wp_shop_storage_folder']);
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
            'STORAGE FOLDER = THEMES',
            $logs
        );
        self::assertContains(
            'attr_update_value = SKIPPED',
            $logs
        );
    }

    public function testWritesVendorSourceAndBrandWithoutItemId(): void
    {
        $meta = [];
        $writer = $this->writer($meta);

        $logs = $writer->write(
            7001,
            new ProductDraftData(
                'Elementor Pro',
                'elementor-pro',
                0,
                '4.2.4',
                '2026-09-05',
                'Elementor',
                '249',
                'https://elementor.com/pro/',
                'elementor-pro-4.2.4.zip',
                'https://wp-shop.org/vendor/elementor-pro-4.2.4.zip',
                0,
                [],
                'RU short',
                'RU long',
                'RU meta',
                'EN short',
                'EN long',
                'EN meta',
                '',
                false,
                false
            )
        );

        self::assertSame('Elementor', $meta['attr_brand_value']);
        self::assertSame('vendor', $meta['_wp_shop_source_type']);
        self::assertArrayNotHasKey(
            '_wp_shop_source_item_id',
            $meta
        );
        self::assertContains('SOURCE TYPE = vendor', $logs);
        self::assertContains('SOURCE ITEM ID = N/A', $logs);
    }

    public function testRoutesVersionlessElementorTemplateKitToTemplates(): void
    {
        $meta = [];
        $writer = $this->writer($meta);

        $logs = $writer->write(6001, $this->templateKitData());

        self::assertSame('—', $meta['attr_version_value']);
        self::assertSame('Шаблоны', $meta['attr_category_value']);
        self::assertSame(
            'template_kit',
            $meta['_wp_shop_product_type']
        );
        self::assertSame(
            'TEMPLATES',
            $meta['_wp_shop_storage_folder']
        );
        self::assertContains(
            'PRODUCT TYPE = template_kit',
            $logs
        );
        self::assertContains(
            'STORAGE FOLDER = TEMPLATES',
            $logs
        );
        self::assertContains(
            'DISPLAY VERSION = VERSIONLESS PLACEHOLDER',
            $logs
        );
    }

    public function testKeepsPublishedTemplateKitVersionForDisplay(): void
    {
        $meta = [];
        $writer = $this->writer($meta);

        $logs = $writer->write(
            6002,
            $this->templateKitData('1.0.4')
        );

        self::assertSame('1.0.4', $meta['attr_version_value']);
        self::assertContains('DISPLAY VERSION = 1.0.4', $logs);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function writer(array &$meta): ProductMetadataWriter
    {
        return new ProductMetadataWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$meta): mixed {
                if ($name === 'update_post_meta') {
                    $meta[(string) $arguments[1]] = $arguments[2];

                    return true;
                }

                if ($name === 'delete_post_meta') {
                    unset($meta[(string) $arguments[1]]);

                    return true;
                }

                return null;
            }
        );
    }

    private function themeData(): ProductDraftData
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

    private function templateKitData(
        string $version = ''
    ): ProductDraftData {
        $sku = 'themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit';

        if ($version !== '') {
            $sku .= '-' . $version;
        }

        $sku .= '.zip';

        return new ProductDraftData(
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            'estateroof',
            43194184,
            $version,
            '2025-12-09',
            'TemplateUp-Pro',
            '249',
            'https://themeforest.net/item/estateroof-roofing-services-elementor-pro-template-kit/43194184',
            $sku,
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
