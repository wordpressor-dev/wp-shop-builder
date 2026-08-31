<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Editorial;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Translation\ProductTranslationResult;

final class ProductEditorialManualDraftTest extends TestCase
{
    public function testManualDraftStagesThenAppliesAndBecomesCurrent(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $manual = $this->manualContent();
        $originalExcerpt = $post['post_excerpt'];
        $originalContent = $post['post_content'];
        $translatedProductId = 0;

        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta),
            translate: static function (
                int $productId,
                string $enShort,
                string $enLong,
                string $enMeta
            ) use (&$translatedProductId): ProductTranslationResult {
                $translatedProductId = $productId;
                self::assertNotSame('', $enShort);
                self::assertNotSame('', $enLong);
                self::assertNotSame('', $enMeta);

                return new ProductTranslationResult(
                    true,
                    ['MISSING = 0', 'OVERALL = READY']
                );
            }
        );

        $logs = $service->saveManualDraft(2789, $manual);

        self::assertContains('MANUAL EDITORIAL DRAFT = SAVED', $logs);
        self::assertSame($originalExcerpt, $post['post_excerpt']);
        self::assertSame($originalContent, $post['post_content']);
        self::assertArrayHasKey('_wp_shop_editorial_manual_draft_v1', $meta);
        self::assertArrayNotHasKey('_wp_shop_editorial_backup_v28', $meta);

        $editor = $service->manualEditor(2789);
        self::assertSame('READY', $editor['status']);
        self::assertTrue($editor['sourceCurrent']);
        self::assertSame($manual, $editor['draft']);

        $applyLogs = $service->applyManual(2789);

        self::assertSame(2789, $translatedProductId);
        self::assertContains('MANUAL EDITORIAL = READY', $applyLogs);
        self::assertContains('EDITORIAL BACKUP = CREATED', $applyLogs);
        self::assertContains('TRANSLATEPRESS SYNC = READY', $applyLogs);
        self::assertContains('MISSING = 0', $applyLogs);
        self::assertContains('OVERALL = READY', $applyLogs);
        self::assertContains('MANUAL DRAFT = CLEARED', $applyLogs);
        self::assertSame($manual['ruShort'], $post['post_excerpt']);
        self::assertSame($manual['ruLong'], $post['post_content']);
        self::assertSame(
            $manual['ruMeta'],
            $meta['surerank_settings_general']['page_description']
        );
        self::assertSame($manual['enShort'], $meta['_wp_shop_en_short_description']);
        self::assertSame($manual['enLong'], $meta['_wp_shop_en_long_description']);
        self::assertSame($manual['enMeta'], $meta['_wp_shop_en_meta_description']);
        self::assertSame('v28-manual', $meta['_wp_shop_editorial_standard']);
        self::assertArrayHasKey('_wp_shop_editorial_backup_v28', $meta);
        self::assertArrayHasKey('_wp_shop_en_target_ru_fingerprint_v2', $meta);
        self::assertArrayHasKey('_wp_shop_en_content_fingerprint_v2', $meta);
        self::assertArrayNotHasKey('_wp_shop_editorial_manual_draft_v1', $meta);

        $preview = $service->preview(2789);
        self::assertSame('CURRENT', $preview['status']);
        self::assertSame('CURRENT', $preview['ruStatus']);
        self::assertSame('CURRENT', $preview['enStatus']);
        self::assertSame('CURRENT', $preview['metaStatus']);
        self::assertSame('MANUAL EDITORIAL', $preview['officialStatus']);
        self::assertSame($manual, $preview['generated']);
    }

    public function testManualDraftStopsWhenSourceChangesAfterSave(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );

        $service->saveManualDraft(2789, $this->manualContent());
        $post['post_excerpt'] = '<p>Changed outside the manual editor.</p>';

        $editor = $service->manualEditor(2789);
        self::assertSame('REVIEW', $editor['status']);
        self::assertFalse($editor['sourceCurrent']);
        self::assertStringContainsString('changed after', $editor['issue']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('changed after');
        $service->applyManual(2789);
    }

    public function testManualDraftRejectsNestedDuplicateBlockTags(): void
    {
        $post = $this->post();
        $meta = $this->meta();
        $service = new ProductEditorialMigrationService(
            $this->caller($post, $meta)
        );
        $manual = $this->manualContent();
        $manual['enLong'] = '<h2>Blocksy Companion Premium</h2>'
            . '<p>Extends Blocksy capabilities.</p>'
            . '<h3><h3>Key features</h3></h3>'
            . '<ul><li>Flexible customization.</li></ul>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nested duplicate block tags');
        $service->saveManualDraft(2789, $manual);
    }

    /** @return array<string,mixed> */
    private function post(): array
    {
        return [
            'ID' => 2789,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_name' => 'blocksy-companion-premium',
            'post_title' => 'Blocksy Companion Premium 2.1.49',
            'post_excerpt' => '<p>Old RU short.</p>',
            'post_content' => '<h2>Old</h2><p>Old RU long.</p>',
        ];
    }

    /** @return array<string,mixed> */
    private function meta(): array
    {
        return [
            'attr_version_value' => '2.1.49',
            '_wp_shop_product_type' => 'plugin',
            'attr_developer_value' => 'CreativeThemes',
            '_wp_shop_en_short_description' => '<p>Old EN short.</p>',
            '_wp_shop_en_long_description' => '<h2>Old</h2><p>Old EN long.</p>',
            '_wp_shop_en_meta_description' => 'Old EN meta.',
            'surerank_settings_general' => [
                'page_description' => 'Old RU meta.',
                'robots' => 'keep',
            ],
        ];
    }

    /** @return array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} */
    private function manualContent(): array
    {
        return [
            'ruShort' => '<p>Blocksy Companion Premium — премиум-плагин для Blocksy.</p>',
            'ruLong' => '<h2>Blocksy Companion Premium</h2>'
                . '<p>Расширяет возможности Blocksy Companion.</p>'
                . '<h3>Основные возможности</h3>'
                . '<ul><li>Гибкая настройка сайта.</li></ul>',
            'ruMeta' => 'Blocksy Companion Premium — премиум-плагин для Blocksy.',
            'enShort' => '<p>Blocksy Companion Premium is a premium plugin for Blocksy.</p>',
            'enLong' => '<h2>Blocksy Companion Premium</h2>'
                . '<p>Extends Blocksy Companion capabilities.</p>'
                . '<h3>Key features</h3>'
                . '<ul><li>Flexible website customization.</li></ul>',
            'enMeta' => 'Blocksy Companion Premium is a premium plugin for Blocksy.',
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
                return ['wordpress', 'blocksy'];
            }

            if ($name === 'wp_update_post') {
                $data = $arguments[0];
                if (is_array($data)) {
                    $post['post_excerpt'] = $data['post_excerpt'] ?? $post['post_excerpt'];
                    $post['post_content'] = $data['post_content'] ?? $post['post_content'];
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
                return '2026-08-31 16:15:00';
            }

            if ($name === 'wp_kses_post' || $name === 'sanitize_textarea_field') {
                return (string) ($arguments[0] ?? '');
            }

            return null;
        };
    }
}
