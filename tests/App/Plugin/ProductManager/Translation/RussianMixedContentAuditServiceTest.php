<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Translation\RussianMixedContentAuditService;

final class RussianMixedContentAuditServiceTest extends TestCase
{
    public function testVisualPurityClassifiesBrandTechAndTranslateWithoutWrites(): void
    {
        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$writes): mixed {
            if ($name === 'get_posts') {
                return [10, 20, 30];
            }

            if ($name === 'get_post_field') {
                $field = (string) ($arguments[0] ?? '');
                $productId = (int) ($arguments[1] ?? 0);

                if ($field === 'post_title') {
                    return match ($productId) {
                        10 => 'Goya — минималистичная WooCommerce-тема',
                        20 => 'Technical Test',
                        30 => 'Code Only Product',
                        default => '',
                    };
                }

                if ($field === 'post_excerpt') {
                    return match ($productId) {
                        10 => '<p>Goya использует WooCommerce и AJAX, '
                            . 'но Quick View и zoom нужно перевести.</p>',
                        20 => '<p>Поддерживаются REST API, SEO, CSS, HTML, PHP, '
                            . 'JSON-LD, Open Graph и Google Maps API.</p>',
                        30 => '<code>Quick View storefront</code> Русское описание.',
                        default => '',
                    };
                }

                if ($field === 'post_content') {
                    return match ($productId) {
                        30 => '[demo text="Mega Menu"] '
                            . 'Документация https://example.com/quick-view',
                        default => '',
                    };
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                return ['page_description' => 'Русское описание для поисковой выдачи.'];
            }

            if (
                str_starts_with($name, 'update_')
                || $name === 'wp_update_post'
            ) {
                $writes[] = $name;
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));
        $rows = $audit->scan(0, 25);

        self::assertCount(3, $rows);

        self::assertSame('REVIEW', $rows[0]->status);
        $first = [];
        foreach ($rows[0]->findings as $finding) {
            $first[$finding->fragment] = $finding->classification;
        }

        self::assertSame('BRAND_NAME', $first['Goya'] ?? null);
        self::assertSame('BRAND_NAME', $first['WooCommerce'] ?? null);
        self::assertSame('TECH_ALLOWED', $first['AJAX'] ?? null);
        self::assertSame('TRANSLATE', $first['Quick View'] ?? null);
        self::assertSame('TRANSLATE', $first['zoom'] ?? null);

        self::assertSame('INFO', $rows[1]->status);
        self::assertSame(
            ['TECH_ALLOWED'],
            array_values(array_unique(array_map(
                static fn($finding): string => $finding->classification,
                $rows[1]->findings
            )))
        );

        self::assertSame('CLEAN', $rows[2]->status);
        self::assertSame([], $rows[2]->findings);
        self::assertSame([], $writes);
    }

    public function testGoyaUiAndFeatureTermsAreTranslateCandidates(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [100];
            }

            if ($name === 'get_post_field') {
                $field = (string) ($arguments[0] ?? '');

                if ($field === 'post_title') {
                    return 'Goya — минималистичная WooCommerce-тема для магазина';
                }

                if ($field === 'post_excerpt') {
                    return '<p>Для каталога предусмотрены AJAX Add-to-Cart, '
                        . 'Quick View, разные swatches и sticky bar товара.</p>';
                }

                if ($field === 'post_content') {
                    return '<p>В навигации доступны Mega Menu и Header. '
                        . 'Также используются featured video, zoom и storefront.</p>'
                        . '<p>WooCommerce, AJAX, API, SEO и CSS допустимы.</p>';
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                return ['page_description' => 'Русское описание для поисковой выдачи.'];
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));
        $rows = $audit->scan(0, 25);

        self::assertCount(1, $rows);
        self::assertSame('REVIEW', $rows[0]->status);

        $translate = [];
        $allowed = [];
        $brands = [];

        foreach ($rows[0]->findings as $finding) {
            if ($finding->classification === 'TRANSLATE') {
                $translate[] = $finding->fragment;
            } elseif ($finding->classification === 'TECH_ALLOWED') {
                $allowed[] = $finding->fragment;
            } elseif ($finding->classification === 'BRAND_NAME') {
                $brands[] = $finding->fragment;
            }
        }

        foreach (
            [
                'AJAX Add-to-Cart',
                'Quick View',
                'swatches',
                'sticky bar',
                'Mega Menu',
                'Header',
                'featured video',
                'zoom',
                'storefront',
            ] as $expected
        ) {
            self::assertContains($expected, $translate);
        }

        self::assertContains('WooCommerce', $brands);
        foreach (['AJAX', 'API', 'SEO', 'CSS'] as $expected) {
            self::assertContains($expected, $allowed);
        }
    }

    public function testDiviMixedCopyKeepsBrandAndFlexboxButFlagsEnglishUiCopy(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [110];
            }

            if ($name === 'get_post_field') {
                $field = (string) ($arguments[0] ?? '');

                if ($field === 'post_title') {
                    return 'Divi 5 — визуальная WordPress-тема и конструктор сайта';
                }

                if ($field === 'post_excerpt') {
                    return '<p>Divi 5 от Elegant Themes объединяет WordPress-тему '
                        . 'и визуальный builder. С помощью drag-and-drop интерфейса '
                        . 'можно создавать landing pages, а Theme Builder управляет '
                        . 'headers, footers, templates и archives.</p>';
                }

                if ($field === 'post_content') {
                    return '<p>Поддерживаются CSS, Flexbox layouts, responsive editing, '
                        . 'reusable styles, native modules и design systems.</p>';
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                return ['page_description' => 'Русское описание для поисковой выдачи.'];
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));
        $rows = $audit->scan(0, 25);

        self::assertCount(1, $rows);
        self::assertSame('REVIEW', $rows[0]->status);

        $classifications = [];
        foreach ($rows[0]->findings as $finding) {
            $classifications[$finding->fragment] = $finding->classification;
        }

        self::assertSame('BRAND_NAME', $classifications['Divi'] ?? null);
        self::assertSame('BRAND_NAME', $classifications['Elegant Themes'] ?? null);
        self::assertSame('BRAND_NAME', $classifications['WordPress'] ?? null);
        self::assertSame('TECH_ALLOWED', $classifications['CSS'] ?? null);
        self::assertSame('TECH_ALLOWED', $classifications['Flexbox'] ?? null);

        foreach (
            [
                'builder',
                'drag-and-drop',
                'landing pages',
                'Theme Builder',
                'headers',
                'footers',
                'templates',
                'archives',
                'layouts',
                'responsive editing',
                'reusable styles',
                'native modules',
                'design systems',
            ] as $expected
        ) {
            self::assertSame(
                'TRANSLATE',
                $classifications[$expected] ?? null,
                $expected
            );
        }
    }

    public function testTitleDerivedFragmentsStayBrandNames(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [40, 50, 60];
            }

            if ($name === 'get_post_field') {
                $field = (string) ($arguments[0] ?? '');
                $productId = (int) ($arguments[1] ?? 0);

                if ($field === 'post_title') {
                    return match ($productId) {
                        40 => 'Duplicator Pro',
                        50 => 'Essential Blocks Pro',
                        60 => 'WP All Import Pro',
                        default => '',
                    };
                }

                if ($field === 'post_excerpt') {
                    return match ($productId) {
                        40 => 'Duplicator Pro Duplicator Pro — плагин WordPress.',
                        50 => 'Gutenberg Essential Blocks Pro — набор блоков.',
                        60 => 'WP All Import Pro – Drag & Drop Import for CSV — импорт данных.',
                        default => '',
                    };
                }

                if ($field === 'post_content') {
                    return '';
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                return ['page_description' => 'Русское описание для поисковой выдачи.'];
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));
        $rows = $audit->scan(0, 25);

        self::assertSame('INFO', $rows[0]->status);
        self::assertSame(
            ['BRAND_NAME'],
            array_values(array_unique(array_map(
                static fn($finding): string => $finding->classification,
                $rows[0]->findings
            )))
        );

        self::assertSame('INFO', $rows[1]->status);
        self::assertSame(
            ['BRAND_NAME'],
            array_values(array_unique(array_map(
                static fn($finding): string => $finding->classification,
                $rows[1]->findings
            )))
        );

        self::assertSame('REVIEW', $rows[2]->status);
        self::assertContains(
            'TRANSLATE',
            array_map(
                static fn($finding): string => $finding->classification,
                $rows[2]->findings
            )
        );
    }

    public function testTreatsLinkedProductNameAsBrandName(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [70];
            }

            if ($name === 'get_post_field') {
                $field = (string) ($arguments[0] ?? '');

                if ($field === 'post_title') {
                    return 'Envira Gallery';
                }

                if ($field === 'post_content') {
                    return 'Совместимость с Justified Image Grid Premium.';
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                return ['page_description' => 'Русское описание для поисковой выдачи.'];
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));
        $rows = $audit->scan(0, 25);

        self::assertCount(1, $rows);
        self::assertSame('INFO', $rows[0]->status);
        self::assertContains(
            'Justified Image Grid Premium',
            array_map(
                static fn($finding): string => $finding->fragment,
                $rows[0]->findings
            )
        );
        self::assertSame(
            ['BRAND_NAME'],
            array_values(array_unique(array_map(
                static fn($finding): string => $finding->classification,
                $rows[0]->findings
            )))
        );
    }

    public function testCandidateCountUsesFullWooCommerceCatalog(): void
    {
        $captured = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$captured): mixed {
            if ($name === 'get_posts') {
                $captured = (array) ($arguments[0] ?? []);

                return [1, 2, 3, 4];
            }

            return null;
        };

        $audit = new RussianMixedContentAuditService($call(...));

        self::assertSame(4, $audit->candidateCount());
        self::assertSame('product', $captured['post_type'] ?? null);
        self::assertSame(-1, $captured['posts_per_page'] ?? null);
        self::assertSame(
            ['publish', 'draft', 'private'],
            $captured['post_status'] ?? null
        );
    }
}
