<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;

final class ProductTechnicalTypeOverrideTest extends TestCase
{
    public function testTechnicalTypeOverridePreviewsAppliesAndRestoresWithoutTouchingCatalogOrContent(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $originalPost = $post;
        $originalCategory = $meta['attr_category_value'];

        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $editor = $service->technicalTypeEditor(3483);

        self::assertSame('theme', $editor['resolvedType']);
        self::assertSame('', $editor['storedType']);
        self::assertStringContainsString('Темы', $editor['catalogCategory']);
        self::assertFalse($editor['backupAvailable']);
        self::assertFalse($editor['hasManualDraft']);

        $preview = $service->previewTechnicalTypeOverride(3483, 'plugin');

        self::assertSame('READY', $preview['status']);
        self::assertSame('theme', $preview['fromType']);
        self::assertSame('plugin', $preview['toType']);
        self::assertArrayNotHasKey('_wp_shop_product_type', $meta);
        self::assertSame($originalPost, $post);
        self::assertSame($originalCategory, $meta['attr_category_value']);

        $logs = $service->applyTechnicalTypeOverride(
            3483,
            'plugin',
            $preview['sourceFingerprint']
        );

        self::assertContains('TECHNICAL TYPE OVERRIDE = READY', $logs);
        self::assertContains('TECHNICAL TYPE BACKUP = CREATED', $logs);
        self::assertContains('CATALOG CATEGORY = PRESERVED / NOT WRITTEN', $logs);
        self::assertContains('PRODUCT CONTENT WRITES = NO', $logs);
        self::assertSame('plugin', $meta['_wp_shop_product_type']);
        self::assertArrayHasKey('_wp_shop_product_type_manual_backup_v1', $meta);
        self::assertSame(
            '',
            $meta['_wp_shop_product_type_manual_backup_v1']['stored_type']
        );
        self::assertSame($originalPost, $post);
        self::assertSame($originalCategory, $meta['attr_category_value']);

        $after = $service->technicalTypeEditor(3483);
        self::assertSame('plugin', $after['resolvedType']);
        self::assertSame('plugin', $after['storedType']);
        self::assertTrue($after['backupAvailable']);

        $restoreLogs = $service->restoreTechnicalTypeOverride(3483);

        self::assertContains('TECHNICAL TYPE RESTORE = READY', $restoreLogs);
        self::assertArrayNotHasKey('_wp_shop_product_type', $meta);
        self::assertSame('theme', $service->technicalTypeEditor(3483)['resolvedType']);
        self::assertSame($originalPost, $post);
        self::assertSame($originalCategory, $meta['attr_category_value']);
    }

    public function testTechnicalTypeOverrideStopsWhenSourceChangesAfterPreview(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $preview = $service->previewTechnicalTypeOverride(3483, 'plugin');
        $post['post_content'] = '<p>Changed after technical type Preview.</p>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('source changed after Preview');

        $service->applyTechnicalTypeOverride(
            3483,
            'plugin',
            $preview['sourceFingerprint']
        );
    }

    public function testTechnicalTypeOverrideBlocksStaleManualDraft(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $meta['_wp_shop_editorial_manual_draft_v1'] = [
            'version' => '1',
            'content' => [],
        ];
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $preview = $service->previewTechnicalTypeOverride(3483, 'plugin');

        self::assertSame('REVIEW', $preview['status']);
        self::assertStringContainsString('Manual RU+EN draft exists', $preview['issue']);
        self::assertArrayNotHasKey('_wp_shop_product_type', $meta);
    }

    /** @return array<string,mixed> */
    private function post(): array
    {
        return [
            'ID' => 3483,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_name' => 'jetthemecore',
            'post_title' => 'JetThemeCore 2.2.2',
            'post_excerpt' => '<p>JetThemeCore old RU short.</p>',
            'post_content' => '<p>JetThemeCore old RU long.</p>',
        ];
    }

    /** @return array<string,mixed> */
    private function meta(): array
    {
        return [
            'attr_version_value' => '2.2.2',
            'attr_category_value' => 'Темы',
            'attr_developer_value' => 'Crocoblock',
            'sales_page' => 'https://crocoblock.com/plugins/jetthemecore/',
            '_wp_shop_en_short_description' => '<p>JetThemeCore old EN short.</p>',
            '_wp_shop_en_long_description' => '<p>JetThemeCore old EN long.</p>',
            '_wp_shop_en_meta_description' => 'JetThemeCore old EN meta.',
            'surerank_settings_general' => [
                'page_description' => 'JetThemeCore old RU meta.',
            ],
        ];
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
                return ['Темы'];
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
                return '2026-08-31 18:30:00';
            }

            if ($name === 'wp_kses_post' || $name === 'sanitize_textarea_field') {
                return (string) ($arguments[0] ?? '');
            }

            return null;
        };
    }
}
