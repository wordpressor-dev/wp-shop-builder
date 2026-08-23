<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressDictionary;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class TranslatePressDictionaryTest extends TestCase
{
    public function testClassifiesExactKeepFillAndMissing(): void
    {
        $database = new TranslationDictionaryDatabase([
            [
                'id' => 1,
                'original' => 'Точно',
                'translated' => 'Exact',
                'status' => 2,
                'original_id' => 0,
            ],
            [
                'id' => 2,
                'original' => 'Готово',
                'translated' => 'Existing finished',
                'status' => 2,
                'original_id' => 0,
            ],
            [
                'id' => 3,
                'original' => 'Заполнить &amp; сохранить',
                'translated' => '',
                'status' => 0,
                'original_id' => 0,
            ],
        ]);
        $dictionary = new TranslatePressDictionary(
            $database,
            static fn(): mixed => null,
            new TranslationMapBuilder()
        );

        $status = $dictionary->status([
            'Точно' => 'Exact',
            'Готово' => 'Prepared replacement',
            'Заполнить & сохранить' => 'Fill & save',
            'Нет строки' => 'Missing row',
        ]);

        self::assertTrue($status->tableOk);
        self::assertSame(4, $status->total);
        self::assertSame(1, $status->exact);
        self::assertSame(1, $status->keep);
        self::assertSame(1, $status->fill);
        self::assertSame(1, $status->missing);
        self::assertSame(
            ['EXACT', 'KEEP', 'FILL', 'MISSING'],
            array_column($status->items, 'action')
        );
    }

    public function testBacksUpAndFillsOnlyUnfinishedRows(): void
    {
        $database = new TranslationDictionaryDatabase([
            [
                'id' => 10,
                'original' => 'Готовая',
                'translated' => 'Keep existing',
                'status' => 2,
                'original_id' => 0,
            ],
            [
                'id' => 11,
                'original' => 'Заполнить',
                'translated' => '',
                'status' => 0,
                'original_id' => 0,
            ],
        ]);
        $backup = null;
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use (&$backup): mixed {
            return match ($name) {
                'get_option' => false,
                'current_time' => '2026-08-15 12:00:00',
                'add_option' => $backup = $arguments[1],
                default => null,
            };
        };
        $dictionary = new TranslatePressDictionary(
            $database,
            $call(...),
            new TranslationMapBuilder()
        );
        $status = $dictionary->status([
            'Готовая' => 'Prepared but do not replace',
            'Заполнить' => 'Fill this',
        ]);

        $dictionary->backup(5028, 'aabbe', $status);
        $filled = $dictionary->fill($status);

        self::assertSame(1, $filled);
        self::assertIsArray($backup);
        self::assertSame(5028, $backup['product_id']);
        self::assertSame('aabbe', $backup['slug']);
        self::assertCount(1, $backup['rows']);
        self::assertSame(11, $backup['rows'][0]['id']);
        self::assertSame(
            [
                [
                    'table' => 'wp_trp_dictionary_ru_ru_en_us',
                    'data' => [
                        'translated' => 'Fill this',
                        'status' => 2,
                    ],
                    'where' => ['id' => 11],
                ],
            ],
            $database->updates
        );
    }

    public function testReportsMissingWhenDictionaryTableDoesNotExist(): void
    {
        $database = new TranslationDictionaryDatabase([]);
        $database->tableExists = false;
        $dictionary = new TranslatePressDictionary(
            $database,
            static fn(): mixed => null,
            new TranslationMapBuilder()
        );

        $status = $dictionary->status([
            'Строка' => 'String',
        ]);

        self::assertFalse($status->tableOk);
        self::assertSame(1, $status->missing);
    }
}

final class TranslationDictionaryDatabase implements
    DatabaseConnectionInterface
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    /** @var list<array{table: string, data: array<string, mixed>, where: array<string, mixed>}> */
    public array $updates = [];

    public bool $tableExists = true;

    /**
     * @param list<array<string, mixed>> $rows
     */
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
        if (! $this->tableExists) {
            return null;
        }

        return [
            'table' => 'wp_trp_dictionary_ru_ru_en_us',
        ];
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
