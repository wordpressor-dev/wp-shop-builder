<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialResidualSafetyTest extends TestCase
{
    public function testTranslatePressBusinessEditionIsNotAProjectTopic(): void
    {
        $ruShort = 'TranslatePress Business – Translate Multilingual sites with AI Translation — '
            . 'профессиональный плагин для создания многоязычных сайтов. '
            . 'Позволяет переводить весь контент, включая динамический, с визуальным редактором. '
            . 'Поддержка SEO и 3+ языков.';

        $draft = (new ProductEditorialDraftBuilder())->build(
            'TranslatePress Business – Translate Multilingual sites with AI Translation',
            'Cozmoslabs',
            CatalogProductType::PLUGIN,
            [],
            '',
            ['ruShort' => $ruShort]
        );

        self::assertStringNotContainsString('ориентированных на бизнес', $draft['ruLong']);
        self::assertStringNotContainsString('focused on business', $draft['enLong']);
    }

    public function testWooCommerceSeoGrammarIsNormalized(): void
    {
        $ruShort = 'Yoast WooCommerce SEO — это специализированный плагин, который улучшает '
            . 'видимость интернет-магазина в поиске с помощью расширенной структурированной данных, '
            . 'AI-генерации метатегов и точечного SEO-анализа товарных страниц.';

        $draft = (new ProductEditorialDraftBuilder())->build(
            'Yoast WooCommerce SEO',
            'Yoast',
            CatalogProductType::PLUGIN,
            ['woocommerce'],
            '',
            ['ruShort' => $ruShort]
        );

        self::assertStringContainsString('расширенных структурированных данных', $draft['ruShort']);
        self::assertStringNotContainsString('расширенной структурированной данных', $draft['ruShort']);
        self::assertStringContainsString('ориентированных на интернет-магазины', $draft['ruLong']);
        self::assertStringNotContainsString('в тематике интернет-магазины', $draft['ruLong']);
    }

    public function testRichThemeLegacyCannotOverridePluginTechnicalType(): void
    {
        $short = 'Лёгкая premium-тема WordPress с сотнями настроек, Starter Sites и '
            . 'block-based theme building для быстрых бизнес-сайтов, блогов и магазинов.';
        $long = '<h2>GeneratePress Premium — лёгкая и гибкая WordPress-тема</h2>'
            . '<p>GeneratePress Premium расширяет бесплатную тему GeneratePress '
            . 'сотнями настроек дизайна и layout.</p>'
            . '<p>Тема ориентирована на производительность и подходит для бизнес-сайтов, '
            . 'блогов и WooCommerce-магазинов.</p>'
            . '<h3>Основные возможности</h3>'
            . '<ul><li>сотни design и layout controls;</li><li>готовые Starter Sites;</li></ul>'
            . '<p>GeneratePress Premium особенно подходит разработчикам и владельцам сайтов, '
            . 'которым нужна минималистичная база без лишнего bloat.</p>';
        $post = [
            'ID' => 2827,
            'post_type' => 'product',
            'post_title' => 'GeneratePress Premium 2.5.6',
            'post_excerpt' => $short,
            'post_content' => $long,
        ];
        $metaDescription = 'Быстрый и легкий WordPress-шаблон с модульной структурой. '
            . 'Идеален для SEO и адаптивен. '
            . 'Предлагает кастомизацию без потери производительности.';
        $meta = [
            'attr_version_value' => '2.5.6',
            '_wp_shop_product_type' => 'plugin',
            'attr_developer_value' => 'Tom Usborne',
            '_wp_shop_source_update_date' => '',
            'surerank_settings_general' => [
                'page_description' => $metaDescription,
            ],
        ];

        $service = new ProductEditorialMigrationService($this->caller($post, $meta));
        $preview = $service->preview(2827);

        self::assertSame('plugin', $preview['productType']);
        self::assertStringContainsString(
            'премиум-плагин для GeneratePress',
            $preview['generated']['ruShort']
        );
        self::assertStringNotContainsString('premium-тема', $preview['generated']['ruShort']);
        self::assertStringNotContainsString('WordPress-шаблон', $preview['generated']['ruMeta']);
        self::assertNotSame($long, $preview['generated']['ruLong']);
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $meta
     * @return \Closure(string,mixed...): mixed
     */
    private function caller(array &$post, array &$meta): \Closure
    {
        return static function (string $name, mixed ...$arguments) use (&$post, &$meta): mixed {
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
                return '2026-08-31 14:00:00';
            }
            return null;
        };
    }
}
