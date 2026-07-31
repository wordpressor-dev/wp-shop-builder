<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Exception\DatabaseOperationFailed;
use WPShop\App\Plugin\Database\WordPressDatabaseConnection;

final class WordPressDatabaseConnectionTest extends TestCase
{
    public function testInsertsDataAndReturnsIdentifier(): void
    {
        $receivedTable = null;
        $receivedData = null;
        $receivedFormats = null;

        $connection = new WordPressDatabaseConnection(
            static function (
                string $table,
                array $data,
                array $formats
            ) use (
                &$receivedTable,
                &$receivedData,
                &$receivedFormats
            ): int {
                $receivedTable = $table;
                $receivedData = $data;
                $receivedFormats = $formats;

                return 42;
            },
            static fn (
                string $sql,
                array $parameters
            ): string => $sql,
            static fn (string $sql): ?array => null
        );

        $insertId = $connection->insert(
            'wp_wps_blueprints',
            [
                'slug' => 'example',
                'type' => 'plugin',
            ],
            [
                '%s',
                '%s',
            ]
        );

        self::assertSame(42, $insertId);
        self::assertSame(
            'wp_wps_blueprints',
            $receivedTable
        );

        self::assertSame(
            [
                'slug' => 'example',
                'type' => 'plugin',
            ],
            $receivedData
        );

        self::assertSame(
            [
                '%s',
                '%s',
            ],
            $receivedFormats
        );
    }

    public function testPreparesAndFetchesSingleRow(): void
    {
        $receivedQuery = null;

        $connection = new WordPressDatabaseConnection(
            static fn (
                string $table,
                array $data,
                array $formats
            ): int => 1,
            static function (
                string $sql,
                array $parameters
            ): string {
                return sprintf(
                    $sql,
                    $parameters[0]
                );
            },
            static function (
                string $sql
            ) use (&$receivedQuery): array {
                $receivedQuery = $sql;

                return [
                    'id' => '7',
                    'slug' => 'example',
                ];
            }
        );

        $row = $connection->fetchOne(
            'SELECT * FROM table WHERE id = %d',
            [7]
        );

        self::assertSame(
            'SELECT * FROM table WHERE id = 7',
            $receivedQuery
        );

        self::assertSame(
            [
                'id' => '7',
                'slug' => 'example',
            ],
            $row
        );
    }

    public function testReturnsNullWhenRowDoesNotExist(): void
    {
        $connection = new WordPressDatabaseConnection(
            static fn (
                string $table,
                array $data,
                array $formats
            ): int => 1,
            static fn (
                string $sql,
                array $parameters
            ): string => $sql,
            static fn (string $sql): ?array => null
        );

        self::assertNull(
            $connection->fetchOne(
                'SELECT * FROM table'
            )
        );
    }

    public function testWrapsInsertFailure(): void
    {
        $connection = new WordPressDatabaseConnection(
            static function (
                string $table,
                array $data,
                array $formats
            ): int {
                throw new RuntimeException(
                    'Native database error.'
                );
            },
            static fn (
                string $sql,
                array $parameters
            ): string => $sql,
            static fn (string $sql): ?array => null
        );

        $this->expectException(
            DatabaseOperationFailed::class
        );

        $this->expectExceptionMessage(
            'Database operation "insert" failed'
        );

        $connection->insert(
            'wp_wps_blueprints',
            ['slug' => 'example'],
            ['%s']
        );
    }
}
