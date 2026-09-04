<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialTranslationPreflightTest extends TestCase
{
    public function testStaleEnglishStructureStopsBeforeWriteAndCurrentPackOverridesBackupEnglish(): void
    {
        $oldRu = '<strong>Elessi - WooCommerce AJAX WordPress Theme - RTL support</strong>'
            . ' — современная WooCommerce тема с AJAX-фильтрами и поддержкой RTL.';
        $staleEn = '<h2>Elessi</h2>'
            . '<p>Old English paragraph one with enough text to keep this legacy page structured.</p>'
            . '<p>Old English paragraph two describes page building and store integrations.</p>'
            . '<p>Old English paragraph three describes multilingual support and responsive layouts.</p>'
            . '<h3>Old features</h3><ul><li>Elementor support.</li><li>WooCommerce support.</li>'
            . '<li>WPML support.</li><li>RTL support.</li><li>Gutenberg support.</li></ul>'
            . '<p>Additional old English content makes this deliberately richer than the new target RU.</p>';
        $post = [
            'ID' => 2792,
            'post_type' => 'product',
            'post_title' => 'Elessi - WooCommerce AJAX WordPress Theme - RTL support 6.6.3',
            'post_excerpt' => $oldRu,
            'post_content' => $oldRu,
        ];
        $meta = [
            'attr_version_value' => '6.6.3',
            '_wp_shop_product_type' => 'theme',
            'attr_developer_value' => 'NasaTheme',
            '_wp_shop_en_short_description' => '<p>Old English short.</p>',
            '_wp_shop_en_long_description' => $staleEn,
            '_wp_shop_en_meta_description' => 'Old English meta description for Elessi '
                . 'WooCommerce theme and store projects.',
            'surerank_settings_general' => [
                'page_description' => 'Современная WooCommerce тема с AJAX-фильтрами и поддержкой RTL.',
            ],
            '_wp_shop_editorial_backup_v28' => [
                'created_at' => '2026-08-30 20:00:00',
                'ruShort' => $oldRu,
                'ruLong' => $oldRu,
                'ruMeta' => 'Современная WooCommerce тема с AJAX-фильтрами и поддержкой RTL.',
                'enShort' => '<p>Backup old English short.</p>',
                'enLong' => $staleEn,
                'enMeta' => 'Backup old English meta description for Elessi WooCommerce theme.',
            ],
        ];
        $writes = 0;
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta, $writes)
        );

        $preview = $service->preview(2792);

        self::assertSame('STOP', $preview['status']);
        self::assertSame('REVIEW', $preview['enStatus']);

        try {
            $service->apply(2792);
            self::fail('Expected stale EN structure to stop apply.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('structurally incompatible', $exception->getMessage());
        }
        self::assertSame(0, $writes);

        $meta['_wp_shop_en_short_description'] = $preview['generated']['ruShort'];
        $meta['_wp_shop_en_long_description'] = $preview['generated']['ruLong'];
        $meta['_wp_shop_en_meta_description'] = $preview['generated']['ruMeta'];

        $afterPack = $service->preview(2792);

        self::assertNotSame('STOP', $afterPack['status']);
        self::assertSame(
            $meta['_wp_shop_en_long_description'],
            $afterPack['generated']['enLong']
        );
        self::assertSame(
            'Backup old English meta description for Elessi WooCommerce theme.',
            $meta['_wp_shop_editorial_backup_v28']['enMeta']
        );
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $meta
     * @return \Closure(string,mixed...): mixed
     */
    private function caller(array &$post, array &$meta, int &$writes): \Closure
    {
        return static function (string $name, mixed ...$arguments) use (&$post, &$meta, &$writes): mixed {
            if ($name === 'get_post') {
                return (object) $post;
            }
            if ($name === 'get_post_meta') {
                return $meta[(string) $arguments[1]] ?? '';
            }
            if ($name === 'wp_get_post_terms') {
                return ['woocommerce', 'ecommerce', 'wpml', 'rtl'];
            }
            if ($name === 'wp_update_post') {
                $writes++;
                return (int) $post['ID'];
            }
            if ($name === 'update_post_meta') {
                $writes++;
                $meta[(string) $arguments[1]] = $arguments[2];
                return true;
            }
            if ($name === 'current_time') {
                return '2026-08-30 20:00:00';
            }
            if ($name === 'wp_kses_post' || $name === 'sanitize_textarea_field') {
                return (string) ($arguments[0] ?? '');
            }
            return null;
        };
    }
}
