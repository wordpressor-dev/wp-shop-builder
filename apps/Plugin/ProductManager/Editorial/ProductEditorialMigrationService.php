<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductEditorialMigrationService
{
    private const BACKUP_META = '_wp_shop_editorial_backup_v28';
    private const STANDARD_META = '_wp_shop_editorial_standard';
    private const MIGRATED_AT_META = '_wp_shop_editorial_migrated_at';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly ProductEditorialDraftBuilder $builder = new ProductEditorialDraftBuilder()
    ) {
    }

    /**
     * @return array{
     *   productId: int,
     *   title: string,
     *   baseTitle: string,
     *   status: string,
     *   productType: string,
     *   developer: string,
     *   sourceUpdateDate: string,
     *   ruStatus: string,
     *   enStatus: string,
     *   metaStatus: string,
     *   backupAvailable: bool,
     *   current: array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     *   generated: array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * }
     */
    public function preview(int $productId): array
    {
        $post = ($this->call)('get_post', $productId);
        $row = is_object($post) ? get_object_vars($post) : [];

        if (($row['post_type'] ?? '') !== 'product') {
            throw new RuntimeException('Product not found: ' . $productId);
        }

        $title = trim((string) ($row['post_title'] ?? ''));
        $baseTitle = $this->baseTitle($productId, $title);
        $productType = $this->productType($productId, $baseTitle);
        $developer = $this->meta($productId, 'attr_developer_value');
        $sourceUpdateDate = $this->meta(
            $productId,
            '_wp_shop_source_update_date'
        );
        $current = [
            'ruShort' => (string) ($row['post_excerpt'] ?? ''),
            'ruLong' => (string) ($row['post_content'] ?? ''),
            'ruMeta' => $this->sureRankMeta($productId),
            'enShort' => $this->meta(
                $productId,
                '_wp_shop_en_short_description'
            ),
            'enLong' => $this->meta(
                $productId,
                '_wp_shop_en_long_description'
            ),
            'enMeta' => $this->meta(
                $productId,
                '_wp_shop_en_meta_description'
            ),
        ];
        $generated = $this->builder->build(
            $baseTitle,
            $developer,
            $productType,
            $this->signals($productId, $baseTitle),
            $sourceUpdateDate
        );
        $ruStatus = $this->pairStatus(
            [$current['ruShort'], $current['ruLong']],
            [$generated['ruShort'], $generated['ruLong']]
        );
        $enStatus = $this->tripleStatus(
            [$current['enShort'], $current['enLong'], $current['enMeta']],
            [$generated['enShort'], $generated['enLong'], $generated['enMeta']]
        );
        $metaStatus = $this->singleStatus(
            $current['ruMeta'],
            $generated['ruMeta']
        );
        $backup = ($this->call)(
            'get_post_meta',
            $productId,
            self::BACKUP_META,
            true
        );

        return [
            'productId' => $productId,
            'title' => $title,
            'baseTitle' => $baseTitle,
            'status' => $ruStatus === 'CURRENT'
                && $enStatus === 'CURRENT'
                && $metaStatus === 'CURRENT'
                    ? 'CURRENT'
                    : 'MIGRATE',
            'productType' => $productType,
            'developer' => $developer,
            'sourceUpdateDate' => $sourceUpdateDate,
            'ruStatus' => $ruStatus,
            'enStatus' => $enStatus,
            'metaStatus' => $metaStatus,
            'backupAvailable' => is_array($backup) && $backup !== [],
            'current' => $current,
            'generated' => $generated,
        ];
    }

    /** @return list<string> */
    public function apply(int $productId): array
    {
        $preview = $this->preview($productId);

        if ($preview['status'] === 'CURRENT') {
            return [
                'EDITORIAL MIGRATION = NO CHANGE',
                'PRODUCT ID = ' . $productId,
                'EDITORIAL STANDARD = CURRENT',
            ];
        }

        $backup = ($this->call)(
            'get_post_meta',
            $productId,
            self::BACKUP_META,
            true
        );
        $backupLog = 'EDITORIAL BACKUP = REUSED';

        if (! is_array($backup) || $backup === []) {
            $backup = [
                'created_at' => $this->now(),
                'ruShort' => $preview['current']['ruShort'],
                'ruLong' => $preview['current']['ruLong'],
                'ruMeta' => $preview['current']['ruMeta'],
                'enShort' => $preview['current']['enShort'],
                'enLong' => $preview['current']['enLong'],
                'enMeta' => $preview['current']['enMeta'],
            ];
            ($this->call)(
                'update_post_meta',
                $productId,
                self::BACKUP_META,
                $backup
            );
            $backupLog = 'EDITORIAL BACKUP = CREATED';
        }

        $this->writeContent($productId, $preview['generated']);
        ($this->call)(
            'update_post_meta',
            $productId,
            self::STANDARD_META,
            'v27'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            self::MIGRATED_AT_META,
            $this->now()
        );

        return [
            'EDITORIAL MIGRATION = READY',
            'PRODUCT ID = ' . $productId,
            $backupLog,
            'RU SHORT / LONG = UPDATED',
            'SURERANK META DESCRIPTION = UPDATED',
            'EN SHORT / LONG / META = PREPARED',
            'TRANSLATEPRESS = NOT TOUCHED',
            'EDITORIAL STANDARD = v27',
        ];
    }

    /** @return list<string> */
    public function restore(int $productId): array
    {
        $backup = ($this->call)(
            'get_post_meta',
            $productId,
            self::BACKUP_META,
            true
        );

        if (! is_array($backup) || $backup === []) {
            throw new RuntimeException(
                'Editorial backup not found for product ' . $productId
            );
        }

        $content = [
            'ruShort' => (string) ($backup['ruShort'] ?? ''),
            'ruLong' => (string) ($backup['ruLong'] ?? ''),
            'ruMeta' => (string) ($backup['ruMeta'] ?? ''),
            'enShort' => (string) ($backup['enShort'] ?? ''),
            'enLong' => (string) ($backup['enLong'] ?? ''),
            'enMeta' => (string) ($backup['enMeta'] ?? ''),
        ];
        $this->writeContent($productId, $content);
        ($this->call)('delete_post_meta', $productId, self::STANDARD_META);
        ($this->call)('delete_post_meta', $productId, self::MIGRATED_AT_META);

        return [
            'EDITORIAL RESTORE = READY',
            'PRODUCT ID = ' . $productId,
            'RU / EN / META = RESTORED FROM BACKUP',
            'BACKUP = PRESERVED',
        ];
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function writeContent(int $productId, array $content): void
    {
        $updated = ($this->call)(
            'wp_update_post',
            [
                'ID' => $productId,
                'post_excerpt' => (string) ($this->call)(
                    'wp_kses_post',
                    $content['ruShort']
                ),
                'post_content' => (string) ($this->call)(
                    'wp_kses_post',
                    $content['ruLong']
                ),
            ],
            true
        );

        if (is_int($updated) && $updated <= 0) {
            throw new RuntimeException('Product content update failed.');
        }

        if (
            is_object($updated)
            && method_exists($updated, 'get_error_message')
        ) {
            throw new RuntimeException(
                'Product content update failed: '
                . (string) $updated->get_error_message()
            );
        }

        $settings = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );

        if (! is_array($settings)) {
            $settings = [];
        }

        $settings['page_description'] = (string) ($this->call)(
            'sanitize_textarea_field',
            $content['ruMeta']
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            'surerank_settings_general',
            $settings
        );

        foreach (
            [
                '_wp_shop_en_short_description' => $content['enShort'],
                '_wp_shop_en_long_description' => $content['enLong'],
            ] as $key => $value
        ) {
            ($this->call)(
                'update_post_meta',
                $productId,
                $key,
                (string) ($this->call)('wp_kses_post', $value)
            );
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_en_meta_description',
            (string) ($this->call)(
                'sanitize_textarea_field',
                $content['enMeta']
            )
        );
    }

    private function baseTitle(int $productId, string $title): string
    {
        $version = trim($this->meta($productId, 'attr_version_value'));

        if ($version === '' || $version === '—') {
            return $title;
        }

        $suffix = ' ' . $version;

        if (str_ends_with($title, $suffix)) {
            return trim(substr($title, 0, -strlen($suffix)));
        }

        return $title;
    }

    private function productType(int $productId, string $baseTitle): string
    {
        $stored = trim($this->meta($productId, '_wp_shop_product_type'));

        if (in_array(
            $stored,
            [
                CatalogProductType::THEME,
                CatalogProductType::PLUGIN,
                CatalogProductType::TEMPLATE_KIT,
            ],
            true
        )) {
            return $stored;
        }

        $category = strtolower(trim(
            $this->meta($productId, 'attr_category_value')
        ));

        if (in_array($category, ['шаблоны', 'templates'], true)) {
            return CatalogProductType::TEMPLATE_KIT;
        }

        if (in_array($category, ['плагины', 'plugins'], true)) {
            return CatalogProductType::PLUGIN;
        }

        if (in_array($category, ['темы', 'themes'], true)) {
            return CatalogProductType::THEME;
        }

        return CatalogProductType::infer(
            $baseTitle,
            $this->meta($productId, 'sales_page')
        );
    }

    /** @return list<string> */
    private function signals(int $productId, string $baseTitle): array
    {
        $signals = [];

        foreach (['product_tag', 'pa_tags'] as $taxonomy) {
            $terms = ($this->call)(
                'wp_get_post_terms',
                $productId,
                $taxonomy,
                ['fields' => 'names']
            );

            if (! is_array($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (is_scalar($term) && trim((string) $term) !== '') {
                    $signals[] = trim((string) $term);
                }
            }
        }

        $titleParts = preg_split('/[^a-z0-9-]+/i', $baseTitle) ?: [];

        foreach ($titleParts as $part) {
            if (strlen(trim($part)) >= 4) {
                $signals[] = trim($part);
            }
        }

        if (str_contains(strtolower($baseTitle), 'real estate')) {
            $signals[] = 'real estate';
        }

        return array_values(array_unique($signals));
    }

    private function sureRankMeta(int $productId): string
    {
        $settings = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );

        return is_array($settings)
            ? trim((string) ($settings['page_description'] ?? ''))
            : '';
    }

    private function meta(int $productId, string $key): string
    {
        $value = ($this->call)(
            'get_post_meta',
            $productId,
            $key,
            true
        );

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param list<string> $current @param list<string> $generated */
    private function pairStatus(array $current, array $generated): string
    {
        if ($this->allEmpty($current)) {
            return 'MISSING';
        }

        return $this->sameList($current, $generated)
            ? 'CURRENT'
            : 'OLD';
    }

    /** @param list<string> $current @param list<string> $generated */
    private function tripleStatus(array $current, array $generated): string
    {
        if ($this->allEmpty($current)) {
            return 'MISSING';
        }

        return $this->sameList($current, $generated)
            ? 'CURRENT'
            : 'OLD';
    }

    private function singleStatus(string $current, string $generated): string
    {
        if (trim($current) === '') {
            return 'MISSING';
        }

        return $this->normalize($current) === $this->normalize($generated)
            ? 'CURRENT'
            : 'OLD';
    }

    /** @param list<string> $values */
    private function allEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $left @param list<string> $right */
    private function sameList(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $value) {
            if (
                $this->normalize($value)
                !== $this->normalize($right[$index] ?? '')
            ) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function now(): string
    {
        $value = ($this->call)('current_time', 'mysql');

        return is_scalar($value) ? (string) $value : '';
    }
}
