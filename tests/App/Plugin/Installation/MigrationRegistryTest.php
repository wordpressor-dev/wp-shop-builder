<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Installation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\SchemaManagerInterface;
use WPShop\App\Plugin\Installation\MigrationRegistry;
use WPShop\App\Plugin\Installation\Migrations\CreateInitialSchema;

final class MigrationRegistryTest extends TestCase
{
    public function testRegistersInitialSchemaMigration(): void
    {
        $registry = MigrationRegistry::create(
            new RegistrySchemaManager()
        );

        $migrations = $registry->all();

        self::assertCount(1, $migrations);

        self::assertInstanceOf(
            CreateInitialSchema::class,
            $migrations[0]
        );

        self::assertSame(
            '0.2.0',
            $migrations[0]->version()
        );
    }
}

final class RegistrySchemaManager implements
    SchemaManagerInterface
{
    public function table(string $name): string
    {
        return 'wp_' . $name;
    }

    public function charsetCollate(): string
    {
        return '';
    }

    public function apply(string $sql): void
    {
    }
}
