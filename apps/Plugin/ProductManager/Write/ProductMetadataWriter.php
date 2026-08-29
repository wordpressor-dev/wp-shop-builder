<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Write;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialDraftBuilder;

final class ProductMetadataWriter implements
    ProductDraftWriterInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        $productType = CatalogProductType::infer(
            $data->baseTitle,
            $data->salesPage
        );
        $categoryLabel = CatalogProductType::categoryLabel(
            $productType
        );
        $storageFolder = CatalogProductType::storageFolder(
            $productType
        );
        $displayVersion = $data->version;
        $editorial = $this->importQueueEditorial($data, $productType);
        $editorialLogs = [];

        if ($editorial !== null) {
            $updated = ($this->call)(
                'wp_update_post',
                [
                    'ID' => $productId,
                    'post_excerpt' => $editorial['ruShort'],
                    'post_content' => $editorial['ruLong'],
                ],
                true
            );

            if (
                is_int($updated)
                && $updated <= 0
            ) {
                throw new RuntimeException(
                    'Editorial auto-draft could not update product content.'
                );
            }

            if (
                is_object($updated)
                && method_exists($updated, 'get_error_message')
            ) {
                throw new RuntimeException(
                    'Editorial auto-draft failed: '
                    . (string) $updated->get_error_message()
                );
            }

            $editorialLogs[] = 'EDITORIAL AUTO-DRAFT = APPLIED';
            $editorialLogs[] = 'RU SHORT / LONG = UPDATED';
            $editorialLogs[] = 'EN SHORT / LONG / META = PREPARED';
        }

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            && trim($displayVersion) === ''
        ) {
            $displayVersion = '—';
        }

        foreach ($this->displayFields() as $metaKey => $field) {
            $this->updateMeta(
                $productId,
                $metaKey,
                $field['value']
            );
            $this->updateMeta(
                $productId,
                '_' . $metaKey,
                $field['key']
            );
        }

        $this->writeAcfValue(
            $productId,
            'field_68d531d09ce86',
            'attr_version_value',
            $displayVersion
        );
        $this->writeAcfValue(
            $productId,
            'field_68d535d793208',
            'attr_category_value',
            $categoryLabel
        );
        $this->writeAcfValue(
            $productId,
            'field_68d5361b6f434',
            'attr_brand_value',
            'Themeforest'
        );
        $this->writeAcfValue(
            $productId,
            'field_68d536325617d',
            'attr_developer_value',
            $data->developer
        );
        $this->writeAcfValue(
            $productId,
            'field_68d244242569b',
            'sales_page',
            $data->salesPage
        );
        $this->writeAcfValue(
            $productId,
            'field_68d5409b18a53',
            'Notes',
            $data->notes
        );

        /*
         * Do not write attr_update_value. The legacy inspected data is
         * inconsistent; the storefront uses post_date for Last updated.
         */
        $this->updateMeta(
            $productId,
            '_wp_shop_source_update_date',
            $data->sourceUpdateDate
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_source_item_id',
            (string) $data->itemId
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_product_type',
            $productType
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_storage_folder',
            $storageFolder
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_en_short_description',
            $editorial['enShort'] ?? $data->enShortDescription
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_en_long_description',
            $editorial['enLong'] ?? $data->enLongDescription
        );
        $this->updateMeta(
            $productId,
            '_wp_shop_en_meta_description',
            $editorial['enMeta'] ?? $data->enMetaDescription
        );

        return array_merge(
            [
                'DISPLAY ACF/META LABELS = UPDATED',
                'ACF PRODUCT VALUES = UPDATED',
                'PRODUCT TYPE = ' . $productType,
                'CATALOG CATEGORY = ' . $categoryLabel,
                'STORAGE FOLDER = ' . $storageFolder,
                'SOURCE ITEM ID = ' . $data->itemId,
                'SOURCE UPDATE DATE = ' . $data->sourceUpdateDate,
                $displayVersion === '—'
                    ? 'DISPLAY VERSION = VERSIONLESS PLACEHOLDER'
                    : 'DISPLAY VERSION = ' . $displayVersion,
            ],
            $editorialLogs,
            [
                ($editorial !== null || $data->hasCompleteEnglishContent())
                    ? 'EN DRAFT CONTENT = SAVED'
                    : 'EN DRAFT CONTENT = NOT COMPLETE',
                'attr_update_value = SKIPPED',
            ]
        );
    }

    /**
     * @return array{
     *   ruShort: string,
     *   ruLong: string,
     *   ruMeta: string,
     *   enShort: string,
     *   enLong: string,
     *   enMeta: string
     * }|null
     */
    private function importQueueEditorial(
        ProductDraftData $data,
        string $productType
    ): ?array {
        if (! str_contains(
            $data->notes,
            'Created from WP Shop Builder Import Queue.'
        )) {
            return null;
        }

        $tagNames = [];

        foreach ($data->tags as $tag) {
            $tagNames[] = $tag->name;
        }

        return (new ProductEditorialDraftBuilder())->build(
            $data->baseTitle,
            $data->developer,
            $productType,
            $tagNames,
            $data->sourceUpdateDate
        );
    }

    /**
     * @return array<string, array{value: string, key: string}>
     */
    private function displayFields(): array
    {
        return [
            'Attr_version' => [
                'value' => '<strong>Версия:</strong>',
                'key' => 'field_68d522389bacc',
            ],
            'version' => [
                'value' => '<strong>Версия:</strong>',
                'key' => 'field_68d522389bacc',
            ],
            'attr_update' => [
                'value' => '<strong>Обновление:</strong>',
                'key' => 'field_68d5320de9197',
            ],
            'attr_category' => [
                'value' => '<strong>Категория:</strong>',
                'key' => 'field_68d535b58968b',
            ],
            'attr_brand' => [
                'value' => '<strong>Бренд:</strong>',
                'key' => 'field_68d53605f8483',
            ],
            'attr_developer' => [
                'value' => '<strong>Разработчик:</strong>',
                'key' => 'field_68d53659989a1',
            ],
            'Attr_tags' => [
                'value' => '<strong>Метки:</strong>',
                'key' => 'field_68d539fd20933',
            ],
        ];
    }

    private function writeAcfValue(
        int $productId,
        string $fieldKey,
        string $fieldName,
        string $value
    ): void {
        $actualName = $this->acfFieldName(
            $fieldKey,
            $productId
        );

        if (
            $actualName !== null
            && $actualName !== $fieldName
        ) {
            throw new RuntimeException(
                'ACF field key mismatch: '
                . $fieldKey
                . ' expected '
                . $fieldName
                . ', got '
                . $actualName
                . '.'
            );
        }

        $this->updateMeta(
            $productId,
            $fieldName,
            $value
        );
        $this->updateMeta(
            $productId,
            '_' . $fieldName,
            $fieldKey
        );
    }

    private function acfFieldName(
        string $fieldKey,
        int $productId
    ): ?string {
        $getFieldObject = $this->optionalCallable(
            'get_field_object'
        );

        if (! $getFieldObject instanceof Closure) {
            return null;
        }

        $field = $getFieldObject(
            $fieldKey,
            $productId,
            false,
            false
        );

        if (! is_array($field)) {
            return null;
        }

        $name = $field['name'] ?? null;

        return is_string($name) && $name !== ''
            ? $name
            : null;
    }

    private function optionalCallable(
        string $name
    ): ?Closure {
        if (! is_callable($name)) {
            return null;
        }

        return Closure::fromCallable($name);
    }

    private function updateMeta(
        int $productId,
        string $key,
        mixed $value
    ): void {
        ($this->call)(
            'update_post_meta',
            $productId,
            $key,
            $value
        );
    }
}
