<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Translation\EnglishContentAuditService;

final class EnglishContentAuditServiceTest extends TestCase
{
    public function testDetectsPreparedEnglishAndTranslatePressIssuesWithoutWrites(): void
    {
        $writes = [];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$writes): mixed {
            if ($name === 'get_posts') {
                return [10, 20];
            }

            if ($name === 'get_post_field') {
                return (int) ($arguments[1] ?? 0) === 10
                    ? 'Clean Product'
                    : 'Mixed Product';
            }

            if ($name === 'get_post_meta') {
                $productId = (int) ($arguments[0] ?? 0);
                $key = (string) ($arguments[1] ?? '');

                if ($productId === 10) {
                    return match ($key) {
                        '_wp_shop_en_short_description' => 'Clean short.',
                        '_wp_shop_en_long_description' => 'Clean long.',
                        '_wp_shop_en_meta_description' => 'Clean meta.',
                        default => '',
                    };
                }

                return match ($key) {
                    '_wp_shop_en_short_description' => 'English short.',
                    '_wp_shop_en_long_description' => 'English then русский фрагмент.',
                    '_wp_shop_en_meta_description' => 'English meta.',
                    default => '',
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

        $audit = new EnglishContentAuditService(
            $call(...),
            new EnglishContentAuditDatabase()
        );
        $rows = $audit->scan(0, 25);

        self::assertCount(2, $rows);
        self::assertSame('CLEAN', $rows[0]->status);
        self::assertSame([], $rows[0]->issues);
        self::assertTrue($rows[0]->trpChecked);

        self::assertSame('REVIEW', $rows[1]->status);
        self::assertContains('LONG', $rows[1]->locations);
        self::assertContains('EN_LONG_CYRILLIC', $rows[1]->issues);
        self::assertNotContains('TRP', $rows[1]->locations);
        self::assertCount(1, $rows[1]->issues);
        self::assertTrue($rows[1]->trpChecked);
        self::assertSame([], $writes);
    }

    public function testMarksPreparedMissingFieldsWhenTranslatePressTablesUnavailable(): void
    {
        $call = static function (
            string $name,
            mixed ...$arguments
        ): mixed {
            if ($name === 'get_posts') {
                return [30];
            }

            if ($name === 'get_post_field') {
                return 'Missing EN Product';
            }

            if ($name === 'get_post_meta') {
                return '';
            }

            return null;
        };
        $database = new EnglishContentAuditDatabase();
        $database->tablesAvailable = false;
        $audit = new EnglishContentAuditService(
            $call(...),
            $database
        );

        $rows = $audit->scan(0, 25);

        self::assertCount(1, $rows);
        self::assertSame('REVIEW', $rows[0]->status);
        self::assertContains('EN_SHORT_MISSING', $rows[0]->issues);
        self::assertContains('EN_LONG_MISSING', $rows[0]->issues);
        self::assertContains('EN_META_MISSING', $rows[0]->issues);
        self::assertFalse($rows[0]->trpChecked);
    }
}

final class EnglishContentAuditDatabase implements DatabaseConnectionInterface
{
    public bool $tablesAvailable = true;

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        return 0;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        return 0;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        if (! $this->tablesAvailable) {
            return null;
        }

        $pattern = (string) ($parameters[0] ?? '');

        if (str_contains($pattern, 'dictionary')) {
            return ['table' => 'wp_trp_dictionary_ru_ru_en_us'];
        }

        if (str_contains($pattern, 'original_meta')) {
            return ['table' => 'wp_trp_original_meta'];
        }

        return null;
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        if (str_contains($sql, 'FROM wp_trp_original_meta')) {
            return [
                ['original_id' => 101, 'meta_value' => '10'],
                ['original_id' => 202, 'meta_value' => '20'],
            ];
        }

        if (str_contains($sql, 'FROM wp_trp_dictionary_ru_ru_en_us')) {
            return [
                [
                    'original_id' => 101,
                    'translated' => 'Clean translated block.',
                    'status' => 2,
                ],
                [
                    'original_id' => 202,
                    'translated' => 'Русский фрагмент',
                    'status' => 2,
                ],
                [
                    'original_id' => 202,
                    'translated' => '',
                    'status' => 0,
                ],
            ];
        }

        return [];
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
