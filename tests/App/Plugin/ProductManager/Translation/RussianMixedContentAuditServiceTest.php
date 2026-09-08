<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Translation\RussianMixedContentAuditService;

final class RussianMixedContentAuditServiceTest extends TestCase
{
    public function testClassifiesBrandsTermsAndProseWithoutProductWrites(): void
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
                        10 => 'Yoast Local SEO',
                        20 => 'Example Product',
                        30 => 'Code Only Product',
                        default => '',
                    };
                }

                if ($field === 'post_excerpt') {
                    return match ($productId) {
                        10 => '<p>Интеграция с Google Maps и page cache.</p>',
                        20 => '<p>Удобный Development Tool for Non-Devs для магазина.</p>',
                        30 => '<code>Performance focused toolkit</code> Русское описание.',
                        default => '',
                    };
                }

                if ($field === 'post_content') {
                    return match ($productId) {
                        10 => '<p>Полностью русский текст.</p>',
                        20 => '<p>The Best Backup and Migration для ежедневной работы.</p>',
                        30 => '[demo text="Advanced With Real-Time Guidance and"] '
                            . 'Документация https://example.com/english-page',
                        default => '',
                    };
                }

                return '';
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                if ($key !== 'surerank_settings_general') {
                    return '';
                }

                return match ($productId) {
                    20 => [
                        'page_description' =>
                            'Customer Support Ticket System для сайта.',
                    ],
                    default => ['page_description' => 'Русское meta описание.'],
                };
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

        self::assertSame('INFO', $rows[0]->status);
        self::assertSame(
            ['BRAND_NAME', 'TERM_ONLY'],
            array_values(array_unique(array_map(
                static fn($finding): string => $finding->classification,
                $rows[0]->findings
            )))
        );
        self::assertSame(
            ['Google Maps', 'page cache'],
            array_map(
                static fn($finding): string => $finding->fragment,
                $rows[0]->findings
            )
        );

        self::assertSame('REVIEW', $rows[1]->status);
        self::assertContains(
            'Development Tool for Non-Devs',
            array_map(
                static fn($finding): string => $finding->fragment,
                $rows[1]->findings
            )
        );
        self::assertContains(
            'The Best Backup and Migration',
            array_map(
                static fn($finding): string => $finding->fragment,
                $rows[1]->findings
            )
        );
        self::assertContains(
            'Customer Support Ticket System',
            array_map(
                static fn($finding): string => $finding->fragment,
                $rows[1]->findings
            )
        );
        self::assertNotContains(
            'TERM_ONLY',
            array_map(
                static fn($finding): string => $finding->classification,
                $rows[1]->findings
            )
        );

        self::assertSame('CLEAN', $rows[2]->status);
        self::assertSame([], $rows[2]->findings);
        self::assertSame([], $writes);
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
