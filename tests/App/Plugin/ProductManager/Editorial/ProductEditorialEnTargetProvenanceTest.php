<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialEnTargetProvenanceTest extends TestCase
{
    public function testStalePreparedEnglishStopsMigrationAfterRuTargetChanges(): void
    {
        $ru = 'WP Reset PRO — профессиональный плагин для быстрого сброса базы данных WordPress. '
            . 'Идеален для разработчиков и тестировщиков.';
        $enShort = '<p>WP Reset PRO is a professional WordPress reset plugin.</p>';
        $enLong = '<h2>WP Reset PRO</h2><p>WP Reset PRO is a professional WordPress reset plugin.</p>'
            . '<p>WP Reset PRO is suitable for quickly resetting a WordPress database.</p>';
        $enMeta = 'Professional WordPress reset plugin for database reset workflows.';
        $post = [
            'ID' => 2956,
            'post_type' => 'product',
            'post_title' => 'WP Reset PRO - WordPress Development Tool for Non-Devs 6.18',
            'post_excerpt' => $ru,
            'post_content' => $ru,
        ];
        $meta = [
            'attr_version_value' => '6.18',
            '_wp_shop_product_type' => 'plugin',
            'attr_developer_value' => 'WebFactory Ltd',
            '_wp_shop_source_update_date' => '',
            '_wp_shop_en_short_description' => $enShort,
            '_wp_shop_en_long_description' => $enLong,
            '_wp_shop_en_meta_description' => $enMeta,
            '_wp_shop_en_target_ru_fingerprint_v2' => str_repeat('a', 64),
            '_wp_shop_en_content_fingerprint_v2' => hash(
                'sha256',
                $enShort . "\0" . $enLong . "\0" . $enMeta
            ),
            'surerank_settings_general' => ['page_description' => $ru],
        ];
        $service = new ProductEditorialMigrationService($this->caller($post, $meta));
        $preview = $service->preview(2956);
        self::assertSame('STOP', $preview['status']);
        self::assertSame('REVIEW', $preview['enStatus']);
        self::assertStringContainsString(
            'WP Reset PRO подходит для разработчиков и тестировщиков.',
            $preview['generated']['ruLong']
        );
        self::assertStringNotContainsString(
            'is suitable for quickly resetting a WordPress database',
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
                return '2026-08-31 12:00:00';
            }
            return null;
        };
    }
}
