<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Database;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\WordPressSchemaManager;

final class WordPressSchemaManagerTest extends TestCase
{
    public function testBuildsPrefixedTableName(): void
    {
        $schema = new WordPressSchemaManager(
            'wp_',
            'DEFAULT CHARACTER SET utf8mb4',
            static function (string $sql): void {
            }
        );

        self::assertSame(
            'wp_wps_blueprints',
            $schema->table('wps_blueprints')
        );

        self::assertSame(
            'DEFAULT CHARACTER SET utf8mb4',
            $schema->charsetCollate()
        );
    }

    public function testAppliesSchemaUsingCallback(): void
    {
        $appliedSql = null;

        $schema = new WordPressSchemaManager(
            'wp_',
            '',
            static function (
                string $sql
            ) use (&$appliedSql): void {
                $appliedSql = $sql;
            }
        );

        $schema->apply('CREATE TABLE example;');

        self::assertSame(
            'CREATE TABLE example;',
            $appliedSql
        );
    }

    public function testRejectsUnsafeTableName(): void
    {
        $schema = new WordPressSchemaManager(
            'wp_',
            '',
            static function (string $sql): void {
            }
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $schema->table('invalid-table');
    }
}
