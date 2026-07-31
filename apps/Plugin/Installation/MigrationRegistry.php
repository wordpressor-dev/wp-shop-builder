<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation;

use WPShop\App\Plugin\Database\Contracts\SchemaManagerInterface;
use WPShop\App\Plugin\Installation\Contracts\MigrationInterface;
use WPShop\App\Plugin\Installation\Migrations\CreateInitialSchema;

final readonly class MigrationRegistry
{
    /**
     * @var list<MigrationInterface>
     */
    private array $migrations;

    /**
     * @param list<MigrationInterface> $migrations
     */
    private function __construct(array $migrations)
    {
        $this->migrations = $migrations;
    }

    public static function create(
        SchemaManagerInterface $schema
    ): self {
        return new self([
            new CreateInitialSchema($schema),
        ]);
    }

    /**
     * @return list<MigrationInterface>
     */
    public function all(): array
    {
        return $this->migrations;
    }
}
