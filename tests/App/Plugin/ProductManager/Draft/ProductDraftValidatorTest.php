<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;

final class ProductDraftValidatorTest extends TestCase
{
    public function testAcceptsCompleteDraftData(): void
    {
        self::assertSame(
            [],
            (new ProductDraftValidator())->validate(
                $this->validData()
            )
        );
    }

    public function testAllowsVendorDraftWithoutEnvatoItemId(): void
    {
        $data = new ProductDraftData(
            'Elementor Pro',
            'elementor-pro',
            0,
            '4.2.4',
            '2026-09-05',
            'Elementor',
            '249',
            'https://elementor.com/pro/',
            'elementor-pro-4.2.4.zip',
            'https://wp-shop.org/vendor/elementor-pro-4.2.4.zip',
            0,
            [],
            'RU short',
            'RU long',
            'RU meta',
            'EN short',
            'EN long',
            'EN meta',
            '',
            false,
            false
        );

        self::assertSame(
            [],
            (new ProductDraftValidator())->validate($data)
        );
    }

    public function testRequiresRuEditorialContent(): void
    {
        $data = $this->validData(
            shortDescription: '',
            longDescription: '',
            metaDescription: ''
        );

        self::assertSame(
            [
                'RU Short Description is required.',
                'RU Long Description is required.',
                'SureRank Meta Description is required.',
            ],
            (new ProductDraftValidator())->validate($data)
        );
    }

    public function testRequiresAllEnglishFieldsWhenAnyIsProvided(): void
    {
        $data = $this->validData(
            enShortDescription: 'Short EN',
            enLongDescription: '',
            enMetaDescription: ''
        );

        self::assertContains(
            'EN Short, Long, and Meta must be filled together.',
            (new ProductDraftValidator())->validate($data)
        );
    }

    public function testRejectsInvalidDateSlugAndUrls(): void
    {
        $data = $this->validData(
            slug: 'Bad Slug',
            sourceUpdateDate: '20.04.2025',
            salesPage: 'not-a-url',
            downloadUrl: 'also-not-a-url'
        );

        $errors = (new ProductDraftValidator())->validate($data);

        self::assertContains(
            'Slug must contain lowercase letters, numbers, and hyphens only.',
            $errors
        );
        self::assertContains(
            'Official update date must use YYYY-MM-DD.',
            $errors
        );
        self::assertContains(
            'Sales Page must be a valid URL.',
            $errors
        );
        self::assertContains(
            'Download URL must be a valid URL.',
            $errors
        );
    }

    private function validData(
        string $slug = 'aabbe',
        string $sourceUpdateDate = '2025-04-20',
        string $salesPage = 'https://themeforest.net/item/aabbe/26350912',
        string $downloadUrl = '',
        string $shortDescription = 'RU short',
        string $longDescription = 'RU long',
        string $metaDescription = 'RU meta',
        string $enShortDescription = 'EN short',
        string $enLongDescription = 'EN long',
        string $enMetaDescription = 'EN meta'
    ): ProductDraftData {
        return new ProductDraftData(
            'Aabbe – Digital Marketplace WordPress Theme',
            $slug,
            26350912,
            '6.2.0',
            $sourceUpdateDate,
            'QuomodoTheme',
            '249',
            $salesPage,
            'themeforest-26350912-aabbe-6.2.0.zip',
            $downloadUrl,
            0,
            [],
            $shortDescription,
            $longDescription,
            $metaDescription,
            $enShortDescription,
            $enLongDescription,
            $enMetaDescription,
            'Pre-activated.',
            false,
            false
        );
    }
}
