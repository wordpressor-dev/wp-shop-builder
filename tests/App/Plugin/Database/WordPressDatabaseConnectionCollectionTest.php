<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Exception\DatabaseOperationFailed;
use WPShop\App\Plugin\Database\WordPressDatabaseConnection;

final class WordPressDatabaseConnectionCollectionTest extends TestCase
{
    public function testPreparesAndFetchesCollection(): void
    {
        $receivedSql = null;
        $receivedParameters = null;
        $executedSql = null;

        $rows = [
            [
                'id' => '42',
                'slug' => 'first-plugin',
            ],
            [
                'id' => '43',
                'slug' => 'second-plugin',
            ],
        ];

        $connection = new WordPressDatabaseConnection(
            static fn (
                string $table,
                array $data,
                array $formats
            ): int => 1,
            static function (
                string $sql,
                array $parameters
            ) use (
                &$receivedSql,
                &$receivedParameters
            ): string {
                $receivedSql = $sql;
                $receivedParameters = $parameters;

                return 'prepared collection query';
            },
            static fn (string $sql): ?array => null,
            null,
            static function (
                string $sql
            ) use (
                &$executedSql,
                $rows
            ): array {
                $executedSql = $sql;

                return $rows;
            }
        );

        $result = $connection->fetchAll(
            'SELECT * FROM table WHERE state = %s',
            ['published']
        );

        self::assertSame(
            'SELECT * FROM table WHERE state = %s',
            $receivedSql
        );

        self::assertSame(
            ['published'],
            $receivedParameters
        );

        self::assertSame(
            'prepared collection query',
            $executedSql
        );

        self::assertSame($rows, $result);
    }

    public function testReturnsEmptyCollection(): void
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
            static fn (string $sql): ?array => null,
            null,
            static fn (string $sql): array => []
        );

        self::assertSame(
            [],
            $connection->fetchAll(
                'SELECT * FROM table'
            )
        );
    }

    public function testWrapsCollectionFailure(): void
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
            static fn (string $sql): ?array => null,
            null,
            static function (string $sql): array {
                throw new RuntimeException(
                    'Native collection query failed.'
                );
            }
        );

        $this->expectException(
            DatabaseOperationFailed::class
        );

        $this->expectExceptionMessage(
            'Database operation "fetch all" failed'
        );

        $connection->fetchAll(
            'SELECT * FROM table'
        );
    }
}
