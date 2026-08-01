<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Exception\DatabaseOperationFailed;
use WPShop\App\Plugin\Database\WordPressDatabaseConnection;

final class WordPressDatabaseConnectionIntegerTest extends TestCase
{
    public function testPreparesAndFetchesInteger(): void
    {
        $receivedSql = null;
        $receivedParameters = null;
        $executedSql = null;

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

                return 'prepared integer query';
            },
            static fn (string $sql): ?array => null,
            null,
            null,
            static function (
                string $sql
            ) use (&$executedSql): string {
                $executedSql = $sql;

                return '101';
            }
        );

        $result = $connection->fetchInteger(
            'SELECT COUNT(*) FROM table WHERE state = %s',
            ['published']
        );

        self::assertSame(101, $result);

        self::assertSame(
            'SELECT COUNT(*) FROM table WHERE state = %s',
            $receivedSql
        );

        self::assertSame(
            ['published'],
            $receivedParameters
        );

        self::assertSame(
            'prepared integer query',
            $executedSql
        );
    }

    public function testAcceptsZeroInteger(): void
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
            null,
            static fn (string $sql): int => 0
        );

        self::assertSame(
            0,
            $connection->fetchInteger(
                'SELECT COUNT(*) FROM table'
            )
        );
    }

    public function testWrapsInvalidIntegerResult(): void
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
            null,
            static fn (string $sql): ?string => null
        );

        $this->expectException(
            DatabaseOperationFailed::class
        );

        $this->expectExceptionMessage(
            'Database operation "fetch integer" failed'
        );

        $connection->fetchInteger(
            'SELECT COUNT(*) FROM table'
        );
    }
}
