<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Installation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\SchemaManagerInterface;
use WPShop\App\Plugin\Installation\Migrations\CreateInitialSchema;

final class CreateInitialSchemaTest extends TestCase
{
    public function testMigrationVersionMatchesPluginVersion(): void
    {
        $migration = new CreateInitialSchema(
            new RecordingSchemaManager()
        );

        self::assertSame(
            '0.2.0',
            $migration->version()
        );
    }

    public function testCreatesInitialCoreTables(): void
    {
        $schema = new RecordingSchemaManager();

        $migration = new CreateInitialSchema($schema);
        $migration->up();

        self::assertCount(3, $schema->statements);

        self::assertStringContainsString(
            'CREATE TABLE wp_wps_blueprints',
            $schema->statements[0]
        );

        self::assertStringContainsString(
            'UNIQUE KEY uuid (uuid)',
            $schema->statements[0]
        );

        self::assertStringContainsString(
            'CREATE TABLE wp_wps_releases',
            $schema->statements[1]
        );

        self::assertStringContainsString(
            'UNIQUE KEY blueprint_version '
            . '(blueprint_id,version)',
            $schema->statements[1]
        );

        self::assertStringContainsString(
            'CREATE TABLE wp_wps_manifests',
            $schema->statements[2]
        );

        self::assertStringContainsString(
            'UNIQUE KEY release_id (release_id)',
            $schema->statements[2]
        );

        foreach ($schema->statements as $statement) {
            self::assertStringContainsString(
                'DEFAULT CHARACTER SET utf8mb4',
                $statement
            );
        }
    }
}

final class RecordingSchemaManager implements
    SchemaManagerInterface
{
    /**
     * @var list<string>
     */
    public array $statements = [];

    public function table(string $name): string
    {
        return 'wp_' . $name;
    }

    public function charsetCollate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4';
    }

    public function apply(string $sql): void
    {
        $this->statements[] = $sql;
    }
}
