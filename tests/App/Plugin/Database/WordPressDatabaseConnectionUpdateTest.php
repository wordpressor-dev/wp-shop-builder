<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Exception\DatabaseOperationFailed;
use WPShop\App\Plugin\Database\WordPressDatabaseConnection;

final class WordPressDatabaseConnectionUpdateTest extends TestCase
{
    public function testUpdatesDataAndReturnsAffectedRows(): void
    {
        $receivedTable = null;
        $receivedData = null;
        $receivedWhere = null;
        $receivedFormats = null;
        $receivedWhereFormats = null;

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
            static function (
                string $table,
                array $data,
                array $where,
                array $formats,
                array $whereFormats
            ) use (
                &$receivedTable,
                &$receivedData,
                &$receivedWhere,
                &$receivedFormats,
                &$receivedWhereFormats
            ): int {
                $receivedTable = $table;
                $receivedData = $data;
                $receivedWhere = $where;
                $receivedFormats = $formats;
                $receivedWhereFormats = $whereFormats;

                return 1;
            }
        );

        $affectedRows = $connection->update(
            'wp_wps_blueprints',
            [
                'state' => 'published',
                'updated_at' => '2026-07-31 20:00:00',
            ],
            [
                'id' => 42,
                'deleted_at' => null,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        self::assertSame(1, $affectedRows);

        self::assertSame(
            'wp_wps_blueprints',
            $receivedTable
        );

        self::assertSame(
            [
                'state' => 'published',
                'updated_at' => '2026-07-31 20:00:00',
            ],
            $receivedData
        );

        self::assertSame(
            [
                'id' => 42,
                'deleted_at' => null,
            ],
            $receivedWhere
        );

        self::assertSame(
            [
                '%s',
                '%s',
            ],
            $receivedFormats
        );

        self::assertSame(
            [
                '%d',
                '%s',
            ],
            $receivedWhereFormats
        );
    }

    public function testWrapsUpdateFailure(): void
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
            static function (
                string $table,
                array $data,
                array $where,
                array $formats,
                array $whereFormats
            ): int {
                throw new RuntimeException(
                    'Native update failed.'
                );
            }
        );

        $this->expectException(
            DatabaseOperationFailed::class
        );

        $this->expectExceptionMessage(
            'Database operation "update" failed'
        );

        $connection->update(
            'wp_wps_blueprints',
            ['state' => 'published'],
            ['id' => 42],
            ['%s'],
            ['%d']
        );
    }
}
