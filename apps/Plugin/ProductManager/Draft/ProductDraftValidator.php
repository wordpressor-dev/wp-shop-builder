<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use DateTimeImmutable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductDraftValidator
{
    /**
     * @return list<string>
     */
    public function validate(ProductDraftData $data): array
    {
        $errors = [];
        $productType = CatalogProductType::infer(
            $data->baseTitle,
            $data->salesPage
        );

        foreach (
            [
                'Base title' => $data->baseTitle,
                'Slug' => $data->slug,
                'Official update date' => $data->sourceUpdateDate,
                'Developer' => $data->developer,
                'Price' => $data->price,
                'Sales Page' => $data->salesPage,
                'SKU / ZIP filename' => $data->skuFilename,
                'RU Short Description' => $data->shortDescription,
                'RU Long Description' => $data->longDescription,
                'SureRank Meta Description' => $data->metaDescription,
            ] as $label => $value
        ) {
            if (trim($value) === '') {
                $errors[] = $label . ' is required.';
            }
        }

        if (
            trim($data->version) === ''
            && $productType !== CatalogProductType::TEMPLATE_KIT
        ) {
            $errors[] = 'Version is required for themes and plugins.';
        }

        if ($data->itemId <= 0) {
            $errors[] = 'ThemeForest Item ID must be positive.';
        }

        if (
            $data->slug !== ''
            && preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $data->slug
            ) !== 1
        ) {
            $errors[] = 'Slug must contain lowercase letters, numbers, and hyphens only.';
        }

        if (
            $data->price !== ''
            && (
                ! is_numeric($data->price)
                || (float) $data->price < 0
            )
        ) {
            $errors[] = 'Price must be a non-negative number.';
        }

        if (
            $data->salesPage !== ''
            && filter_var(
                $data->salesPage,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            $errors[] = 'Sales Page must be a valid URL.';
        }

        if (
            $data->downloadUrl !== ''
            && filter_var(
                $data->downloadUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            $errors[] = 'Download URL must be a valid URL.';
        }

        if (
            $data->sourceUpdateDate !== ''
            && ! $this->validDate(
                $data->sourceUpdateDate
            )
        ) {
            $errors[] = 'Official update date must use YYYY-MM-DD.';
        }

        $englishFields = [
            $data->enShortDescription,
            $data->enLongDescription,
            $data->enMetaDescription,
        ];
        $englishFilled = count(
            array_filter(
                $englishFields,
                static fn(string $value): bool =>
                    trim($value) !== ''
            )
        );

        if ($englishFilled > 0 && $englishFilled < 3) {
            $errors[] = 'EN Short, Long, and Meta must be filled together.';
        }

        return $errors;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}
