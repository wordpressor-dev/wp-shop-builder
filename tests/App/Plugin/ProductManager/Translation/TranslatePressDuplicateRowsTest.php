<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressDictionary;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class TranslatePressDuplicateRowsTest extends TestCase
{
    public function testFillsEveryUnfinishedDuplicateRowForSameSource(): void
    {
        $database = new DuplicateRowsDatabase([
            [
                'id' => 101,
                'original' => 'Одинаковый текст',
                'translated' => 'Same text',
                'status' => 2,
                'original_id' => 0,
            ],
            [
                'id' => 102,
                'original' => 'Одинаковый текст',
                'translated' => '',
                'status' => 0,
                'original_id' => 77,
            ],
        ]);
        $backup = null;
        $dictionary = new TranslatePressDictionary(
            $database,
            static function (
                string $name,
                mixed ...$arguments
            ) use (&$backup): mixed {
                return match ($name) {
                    'get_option' => false,
                    'current_time' => '2026-08-29 22:00:00',
                    'add_option' => $backup = $arguments[1],
                    default => null,
                };
            },
            new TranslationMapBuilder()
        );

        $before = $dictionary->status([
            'Одинаковый текст' => 'Same text',
        ]);

        self::assertSame(1, $before->fill);
        self::assertSame('FILL', $before->items[0]['action']);

        $dictionary->backup(4561, 'edubin', $before);
        $filled = $dictionary->fill($before);
        $after = $dictionary->status([
            'Одинаковый текст' => 'Same text',
        ]);

        self::assertSame(1, $filled);
        self::assertIsArray($backup);
        self::assertCount(1, $backup['rows']);
        self::assertSame(102, $backup['rows'][0]['id']);
        self::assertSame(1, $after->exact);
        self::assertSame(0, $after->fill);
        self::assertTrue($after->ready());
        self::assertSame(
            [
                [
                    'table' => 'wp_trp_dictionary_ru_ru_en_us',
                    'data' => [
                        'translated' => 'Same text',
                        'status' => 2,
                    ],
                    'where' => ['id' => 102],
                ],
            ],
            $database->updates
        );
    }
}

final class DuplicateRowsDatabase implements DatabaseConnectionInterface
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    /** @var list<array{table:string,data:array<string,mixed>,where:array<string,mixed>}> */
    public array $updates = [];

    /** @param list<array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        return 1;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
        ];

        foreach ($this->rows as &$row) {
            if ((int) ($row['id'] ?? 0) !== (int) ($where['id'] ?? 0)) {
                continue;
            }

            $row = array_merge($row, $data);
        }
        unset($row);

        return 1;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        return ['table' => 'wp_trp_dictionary_ru_ru_en_us'];
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        $wanted = array_fill_keys($parameters, true);

        return array_values(
            array_filter(
                $this->rows,
                static fn(array $row): bool =>
                    isset($wanted[(string) ($row['original'] ?? '')])
            )
        );
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
