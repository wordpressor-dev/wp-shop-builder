<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialRichEnglishPreservationTest extends TestCase
{
    public function testKeepsTranslatedRichEnglishContentCurrent(): void
    {
        $ruShort = 'Премиальный performance-плагин WordPress с page cache, preload, '
            . 'file optimization, LazyLoad, Delay JS, Remove Unused CSS и database optimization.';
        $ruLong = '<h2>WP Rocket — комплексная оптимизация скорости WordPress</h2>'
            . '<p>WP Rocket автоматически включает page cache и ряд базовых performance-'
            . 'оптимизаций сразу после активации. Плагин создаёт статические HTML-копии '
            . 'страниц, поддерживает preload и помогает ускорить повторные и первые посещения сайта.</p>'
            . '<p>Дополнительные инструменты включают оптимизацию CSS и JavaScript, Delay JS, '
            . 'Remove Unused CSS, LazyLoad, font optimization и database cleanup.</p>'
            . '<h3>Основные возможности</h3><ul><li>автоматический page cache;</li>'
            . '<li>cache preloading;</li><li>CSS и JavaScript optimization;</li></ul>'
            . '<p>WP Rocket подходит сайтам, которым нужен единый performance toolkit без '
            . 'сложной первоначальной настройки и помогает улучшать Core Web Vitals.</p>';
        $ruMeta = 'Премиум-плагин кэширования для мгновенного ускорения WordPress. '
            . 'Автоматическое сжатие, ленивая загрузка и оптимизация Core Web Vitals '
            . 'для максимальной производительности.';

        $enShort = 'Premium WordPress performance plugin with page cache, preload, file '
            . 'optimization, LazyLoad, Delay JS, Remove Unused CSS, and database optimization.';
        $enLong = '<h2>WP Rocket — comprehensive WordPress speed optimization</h2>'
            . '<p>WP Rocket automatically enables page cache and a range of essential '
            . 'performance optimizations immediately after activation. The plugin creates '
            . 'static HTML copies of pages, supports preload, and helps speed up both repeat '
            . 'and first-time visits to the site.</p>'
            . '<p>Additional tools include CSS and JavaScript optimization, Delay JS, '
            . 'Remove Unused CSS, LazyLoad, font optimization, and database cleanup.</p>'
            . '<h3>Key features</h3><ul><li>automatic page cache;</li>'
            . '<li>cache preloading;</li><li>CSS and JavaScript optimization;</li></ul>'
            . '<p>WP Rocket is suitable for websites that need a unified performance toolkit '
            . 'without complicated initial configuration and helps improve Core Web Vitals.</p>';
        $enMeta = 'Premium caching plugin for instant WordPress speed improvements. '
            . 'Automatic compression, lazy loading, and Core Web Vitals optimization '
            . 'for maximum performance.';

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
            '_wp_shop_en_short_description' => $enShort,
            '_wp_shop_en_long_description' => $enLong,
            '_wp_shop_en_meta_description' => $enMeta,
            'surerank_settings_general' => [
                'page_description' => $ruMeta,
            ],
        ];

        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );
        $preview = $service->preview(2798);

        self::assertSame('CURRENT', $preview['status']);
        self::assertSame('CURRENT', $preview['ruStatus']);
        self::assertSame('CURRENT', $preview['enStatus']);
        self::assertSame('CURRENT', $preview['metaStatus']);
        self::assertSame($ruLong, $preview['generated']['ruLong']);
        self::assertSame($enShort, $preview['generated']['enShort']);
        self::assertSame($enLong, $preview['generated']['enLong']);
        self::assertSame($enMeta, $preview['generated']['enMeta']);
        self::assertStringNotContainsString(
            'Before publishing, verify',
            $preview['generated']['enLong']
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
                return '2026-08-30 10:00:00';
            }

            return null;
        };
    }
}
