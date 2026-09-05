<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Write;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;

final class ProductTaxonomyWriterTest extends TestCase
{
    public function testWritesThemeForestTaxonomiesAndAttributeOrder(): void
    {
        $setTerms = [];
        $meta = [];
        $terms = [
            'product_cat:themes' => 1,
            'product_brand:themeforest' => 2,
            'pa_categori:themes' => 3,
            'pa_company:themeforest' => 4,
            'pa_developer:quomodotheme' => 5,
            'product_tag:elementor' => 10,
            'pa_tags:elementor' => 11,
            'product_tag:marketplace' => 12,
            'pa_tags:marketplace' => 13,
        ];

        $writer = new ProductTaxonomyWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (
                &$setTerms,
                &$meta,
                $terms
            ): mixed {
                if ($name === 'sanitize_title') {
                    return strtolower((string) $arguments[0]);
                }

                if ($name === 'get_term_by') {
                    $taxonomy = (string) $arguments[2];
                    $value = (string) $arguments[1];
                    $key = $taxonomy . ':' . strtolower($value);

                    return isset($terms[$key])
                        ? (object) ['term_id' => $terms[$key]]
                        : false;
                }

                if ($name === 'wp_set_object_terms') {
                    $setTerms[(string) $arguments[2]] = $arguments[1];

                    return $arguments[1];
                }

                if ($name === 'update_post_meta') {
                    $meta[(string) $arguments[1]] = $arguments[2];

                    return true;
                }

                if ($name === 'is_wp_error') {
                    return false;
                }

                return null;
            }
        );

        $logs = $writer->write(5028, $this->data());

        self::assertSame([1], $setTerms['product_cat']);
        self::assertSame([2], $setTerms['product_brand']);
        self::assertSame([3], $setTerms['pa_categori']);
        self::assertSame([4], $setTerms['pa_company']);
        self::assertSame([5], $setTerms['pa_developer']);
        self::assertSame([10, 12], $setTerms['product_tag']);
        self::assertSame([11, 13], $setTerms['pa_tags']);
        self::assertSame(
            [
                'pa_categori',
                'pa_company',
                'pa_developer',
                'pa_tags',
            ],
            array_keys($meta['_product_attributes'])
        );
        self::assertContains(
            'TAG POLICY = EXISTING_ONLY; NEW_TAGS_CREATED = 0',
            $logs
        );
    }

    public function testUsesVendorAsBrandAndCompany(): void
    {
        $setTerms = [];
        $terms = [
            'product_cat:plugins' => 31,
            'product_brand:elementor' => 32,
            'pa_categori:plugins' => 33,
            'pa_company:elementor' => 34,
            'pa_developer:elementor' => 35,
        ];
        $writer = new ProductTaxonomyWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$setTerms, $terms): mixed {
                if ($name === 'sanitize_title') {
                    return strtolower(
                        str_replace(
                            ' ',
                            '-',
                            (string) $arguments[0]
                        )
                    );
                }

                if ($name === 'get_term_by') {
                    $taxonomy = (string) $arguments[2];
                    $value = (string) $arguments[1];
                    $key = $taxonomy . ':' . strtolower($value);

                    return isset($terms[$key])
                        ? (object) ['term_id' => $terms[$key]]
                        : false;
                }

                if ($name === 'wp_set_object_terms') {
                    $setTerms[(string) $arguments[2]]
                        = $arguments[1];

                    return $arguments[1];
                }

                if ($name === 'update_post_meta') {
                    return true;
                }

                if ($name === 'is_wp_error') {
                    return false;
                }

                return null;
            }
        );

        $data = new ProductDraftData(
            'Elementor Pro WordPress Plugin',
            'elementor-pro',
            0,
            '4.2.4',
            '2026-09-05',
            'Elementor',
            '249',
            'https://elementor.com/pro/',
            'elementor-pro-4.2.4.zip',
            '',
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
        $logs = $writer->write(7001, $data);

        self::assertSame([31], $setTerms['product_cat']);
        self::assertSame([32], $setTerms['product_brand']);
        self::assertSame([33], $setTerms['pa_categori']);
        self::assertSame([34], $setTerms['pa_company']);
        self::assertSame([35], $setTerms['pa_developer']);
        self::assertContains(
            'product_brand = Elementor',
            $logs
        );
    }

    public function testRoutesTemplateKitToTemplatesCategoryAndAttribute(): void
    {
        $setTerms = [];
        $terms = [
            'product_cat:templates' => 21,
            'product_brand:themeforest' => 2,
            'pa_categori:templates' => 23,
            'pa_company:themeforest' => 4,
            'pa_developer:templateup-pro' => 25,
        ];

        $writer = new ProductTaxonomyWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (
                &$setTerms,
                $terms
            ): mixed {
                if ($name === 'sanitize_title') {
                    return strtolower(str_replace(' ', '-', (string) $arguments[0]));
                }

                if ($name === 'get_term_by') {
                    $taxonomy = (string) $arguments[2];
                    $value = (string) $arguments[1];
                    $key = $taxonomy . ':' . strtolower($value);

                    return isset($terms[$key])
                        ? (object) ['term_id' => $terms[$key]]
                        : false;
                }

                if ($name === 'wp_set_object_terms') {
                    $setTerms[(string) $arguments[2]] = $arguments[1];

                    return $arguments[1];
                }

                if ($name === 'update_post_meta') {
                    return true;
                }

                if ($name === 'is_wp_error') {
                    return false;
                }

                return null;
            }
        );

        $logs = $writer->write(5154, $this->templateKitData());

        self::assertSame([21], $setTerms['product_cat']);
        self::assertSame([23], $setTerms['pa_categori']);
        self::assertContains('product_cat = Шаблоны', $logs);
        self::assertContains('pa_categori = Шаблоны', $logs);
    }

    public function testNeverCreatesMissingCatalogTag(): void
    {
        $insertCalls = 0;
        $writer = new ProductTaxonomyWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$insertCalls): mixed {
                if ($name === 'sanitize_title') {
                    return 'quomodotheme';
                }

                if ($name === 'get_term_by') {
                    $taxonomy = (string) $arguments[2];

                    if (
                        $taxonomy === 'product_tag'
                        || $taxonomy === 'pa_tags'
                    ) {
                        return false;
                    }

                    return (object) ['term_id' => 1];
                }

                if ($name === 'wp_insert_term') {
                    $insertCalls++;

                    return ['term_id' => 99];
                }

                return false;
            }
        );

        try {
            $writer->write(5028, $this->data());
            self::fail('Expected missing existing tag failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(
                'Existing catalog tag disappeared',
                $exception->getMessage()
            );
        }

        self::assertSame(0, $insertCalls);
    }

    public function testCreatesDeveloperAttributeWhenMissing(): void
    {
        $inserted = [];
        $writer = new ProductTaxonomyWriter(
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$inserted): mixed {
                if ($name === 'sanitize_title') {
                    return 'quomodotheme';
                }

                if ($name === 'get_term_by') {
                    $taxonomy = (string) $arguments[2];

                    if ($taxonomy === 'pa_developer') {
                        return false;
                    }

                    return (object) ['term_id' => 1];
                }

                if ($name === 'wp_insert_term') {
                    $inserted[] = [
                        $arguments[0],
                        $arguments[1],
                        $arguments[2],
                    ];

                    return ['term_id' => 5];
                }

                if ($name === 'is_wp_error') {
                    return false;
                }

                return true;
            }
        );

        $writer->write(5028, $this->data(tags: []));

        self::assertSame(
            [
                [
                    'QuomodoTheme',
                    'pa_developer',
                    ['slug' => 'quomodotheme'],
                ],
            ],
            $inserted
        );
    }

    /**
     * @param list<CatalogTag>|null $tags
     */
    private function data(?array $tags = null): ProductDraftData
    {
        return new ProductDraftData(
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            26350912,
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            '249',
            'https://themeforest.net/item/aabbe/26350912',
            'themeforest-26350912-aabbe-6.2.0.zip',
            '',
            0,
            $tags ?? [
                new CatalogTag('elementor', 'elementor'),
                new CatalogTag(
                    'торговая площадка',
                    'marketplace'
                ),
            ],
            'RU short',
            'RU long',
            'RU meta',
            'EN short',
            'EN long',
            'EN meta',
            'Pre-activated.',
            false,
            false
        );
    }

    private function templateKitData(): ProductDraftData
    {
        return new ProductDraftData(
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            'estateroof',
            43194184,
            '',
            '2025-12-09',
            'TemplateUp-Pro',
            '249',
            'https://themeforest.net/item/estateroof-roofing-services-elementor-pro-template-kit/43194184',
            'themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip',
            '',
            0,
            [],
            'RU short',
            'RU long',
            'RU meta',
            '',
            '',
            '',
            'Pre-activated.',
            false,
            false
        );
    }
}
