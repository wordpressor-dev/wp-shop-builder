<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductEditorialMigrationServiceTest extends TestCase
{
    public function testPreviewsAppliesAndRestoresLegacyEditorialContent(): void
    {
        $oldShort = '<strong>Edubin – Education WordPress Theme</strong>'
            . ' — это современная тема WordPress для школ, курсов,'
            . ' университетов и онлайн-образования.';
        $oldLong = '<p>Адаптивный дизайн, интеграция с LMS,'
            . ' расписание занятий, страницы преподавателей, календарь событий,'
            . ' галерея, отзывы и форма обратной связи.</p>';
        $post = [
            'ID' => 4561,
            'post_type' => 'product',
            'post_title' => 'Edubin – Education WordPress Theme 9.6.5',
            'post_excerpt' => $oldShort,
            'post_content' => $oldLong,
        ];
        $meta = [
            'attr_version_value' => '9.6.5',
            '_wp_shop_product_type' => 'theme',
            'attr_developer_value' => 'pixelcurve',
            '_wp_shop_source_update_date' => '2026-08-28',
            'sales_page' => 'https://themeforest.net/item/edubin/24037792',
            '_wp_shop_en_short_description' => 'Old EN short.',
            '_wp_shop_en_long_description' => 'Old EN long.',
            '_wp_shop_en_meta_description' => 'Old EN meta.',
            'surerank_settings_general' => [
                'page_description' => 'Old RU meta.',
                'robots' => 'keep',
            ],
        ];
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $preview = $service->preview(4561);

        self::assertSame(
            'Edubin – Education WordPress Theme',
            $preview['baseTitle']
        );
        self::assertSame('theme', $preview['productType']);
        self::assertSame('MIGRATE', $preview['status']);
        self::assertSame('OLD', $preview['ruStatus']);
        self::assertSame('OLD', $preview['enStatus']);
        self::assertSame('OLD', $preview['metaStatus']);
        self::assertFalse($preview['backupAvailable']);
        self::assertStringContainsString(
            'школ, курсов, университетов',
            $preview['generated']['ruShort']
        );
        self::assertStringContainsString(
            '<h3>Основные возможности</h3>',
            $preview['generated']['ruLong']
        );
        self::assertStringContainsString(
            '<li>расписание занятий.</li>',
            $preview['generated']['ruLong']
        );
        self::assertStringContainsString(
            '<li>страницы преподавателей.</li>',
            $preview['generated']['ruLong']
        );
        self::assertStringContainsString(
            '<h3>Техническая информация</h3>',
            $preview['generated']['ruLong']
        );

        $logs = $service->apply(4561);

        self::assertContains('EDITORIAL BACKUP = CREATED', $logs);
        self::assertContains('TRANSLATEPRESS = NOT TOUCHED', $logs);
        self::assertIsArray($meta['_wp_shop_editorial_backup_v28']);
        self::assertSame('v28', $meta['_wp_shop_editorial_standard']);
        self::assertStringContainsString(
            'школ, курсов, университетов',
            (string) $post['post_excerpt']
        );
        self::assertStringContainsString(
            '<h3>Основные возможности</h3>',
            (string) $post['post_content']
        );
        self::assertStringContainsString(
            '<h3>Техническая информация</h3>',
            (string) $post['post_content']
        );
        self::assertSame(
            'keep',
            $meta['surerank_settings_general']['robots']
        );
        self::assertNotSame(
            'Old RU meta.',
            $meta['surerank_settings_general']['page_description']
        );

        $after = $service->preview(4561);
        self::assertSame('CURRENT', $after['status']);
        self::assertTrue($after['backupAvailable']);

        $restoreLogs = $service->restore(4561);

        self::assertContains('EDITORIAL RESTORE = READY', $restoreLogs);
        self::assertSame($oldShort, $post['post_excerpt']);
        self::assertSame($oldLong, $post['post_content']);
        self::assertSame(
            'Old RU meta.',
            $meta['surerank_settings_general']['page_description']
        );
        self::assertSame(
            'Old EN short.',
            $meta['_wp_shop_en_short_description']
        );
        self::assertArrayHasKey('_wp_shop_editorial_backup_v28', $meta);
        self::assertArrayNotHasKey('_wp_shop_editorial_standard', $meta);
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
                return ['education', 'lms', 'school', 'wordpress'];
            }

            if ($name === 'wp_update_post') {
                $data = $arguments[0];

                if (is_array($data)) {
                    $post['post_excerpt'] = $data['post_excerpt']
                        ?? $post['post_excerpt'];
                    $post['post_content'] = $data['post_content']
                        ?? $post['post_content'];
                }

                return (int) $post['ID'];
            }

            if ($name === 'update_post_meta') {
                $meta[(string) $arguments[1]] = $arguments[2];

                return true;
            }

            if ($name === 'delete_post_meta') {
                unset($meta[(string) $arguments[1]]);

                return true;
            }

            if ($name === 'current_time') {
                return '2026-08-29 14:00:00';
            }

            if (
                $name === 'wp_kses_post'
                || $name === 'sanitize_textarea_field'
            ) {
                return (string) ($arguments[0] ?? '');
            }

            return null;
        };
    }
}
