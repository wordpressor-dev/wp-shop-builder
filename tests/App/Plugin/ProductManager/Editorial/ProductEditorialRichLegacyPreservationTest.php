<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialRichLegacyPreservationTest extends TestCase
{
    public function testKeepsRichRuEditorialContentWhileEnglishIsIncomplete(): void
    {
        $ruShort = 'Премиальный performance-плагин WordPress с page cache, preload, '
            . 'file optimization, LazyLoad, Delay JS, Remove Unused CSS и database optimization.';
        $ruLong = '<h2>WP Rocket – комплексная оптимизация скорости WordPress</h2>'
            . '<p>WP Rocket автоматически включает page cache и ряд базовых '
            . 'performance-оптимизаций сразу после активации. Плагин создаёт '
            . 'статические HTML-копии страниц, поддерживает preload и помогает '
            . 'ускорить повторные и первые посещения сайта.</p>'
            . '<p>Дополнительные инструменты включают оптимизацию CSS и JavaScript, '
            . 'Delay JS, Remove Unused CSS, LazyLoad, font optimization и database '
            . 'cleanup для комплексной оптимизации производительности.</p>'
            . '<h3>Кэширование и preload</h3>'
            . '<p>Плагин автоматически применяет кэширование страниц и preload, '
            . 'чтобы ускорять загрузку и улучшать Core Web Vitals на типичных '
            . 'WordPress-сайтах.</p>';
        $ruMeta = 'Премиум-плагин кэширования для мгновенного ускорения WordPress. '
            . 'Автоматическое сжатие, ленивая загрузка и оптимизация Core Web Vitals '
            . 'для максимальной производительности.';
        $post = [
            'ID' => 2798,
            'post_type' => 'product',
            'post_title' => 'WP Rocket – The Best WordPress Performance Plugin 3.23.2.2',
            'post_excerpt' => $ruShort,
            'post_content' => $ruLong,
        ];
        $meta = [
            'attr_version_value' => '3.23.2.2',
            '_wp_shop_product_type' => 'plugin',
            'attr_developer_value' => 'WP Media',
            '_wp_shop_source_update_date' => '',
            '_wp_shop_en_short_description' => '',
            '_wp_shop_en_long_description' => '',
            '_wp_shop_en_meta_description' => '',
            'surerank_settings_general' => [
                'page_description' => $ruMeta,
            ],
        ];
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $preview = $service->preview(2798);

        self::assertSame('STOP', $preview['status']);
        self::assertSame('plugin', $preview['productType']);
        self::assertSame('CURRENT', $preview['ruStatus']);
        self::assertSame('REVIEW', $preview['enStatus']);
        self::assertSame('CURRENT', $preview['metaStatus']);
        self::assertSame($ruShort, $preview['generated']['ruShort']);
        self::assertSame($ruLong, $preview['generated']['ruLong']);
        self::assertSame($ruMeta, $preview['generated']['ruMeta']);
        self::assertStringNotContainsString(
            '<h2>WP Rocket – The Best WordPress Performance Plugin</h2>',
            $preview['generated']['ruLong']
        );
        self::assertStringContainsString(
            'is a WordPress plugin by WP Media',
            $preview['generated']['enShort']
        );
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $meta
     * @return \Closure(string,mixed...): mixed
     */
    private function caller(array &$post, array &$meta): \Closure
    {
        return static function (
            string $name,
            mixed ...$arguments
        ) use (
            &$post,
            &$meta
        ): mixed {
            if ($name === 'get_post') {
                return (object) $post;
            }

            if ($name === 'get_post_meta') {
                return $meta[(string) $arguments[1]] ?? '';
            }

            if ($name === 'wp_get_post_terms') {
                return [];
            }

            if ($name === 'current_time') {
                return '2026-08-30 09:00:00';
            }

            return null;
        };
    }
}
