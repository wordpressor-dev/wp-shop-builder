<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation;

use InvalidArgumentException;
use Throwable;
use WPShop\App\Plugin\Installation\Contracts\MigrationInterface;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;

final readonly class MigrationRunner
{
    /**
     * @var list<MigrationInterface>
     */
    private array $migrations;

    /**
     * @param list<MigrationInterface> $migrations
     */
    public function __construct(array $migrations)
    {
        usort(
            $migrations,
            static fn (
                MigrationInterface $left,
                MigrationInterface $right
            ): int => version_compare(
                $left->version(),
                $right->version()
            )
        );

        $versions = [];

        foreach ($migrations as $migration) {
            $version = $migration->version();

            if (isset($versions[$version])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Duplicate migration version: %s.',
                        $version
                    )
                );
            }

            $versions[$version] = true;
        }

        $this->migrations = $migrations;
    }

    public function run(
        ?string $installedVersion,
        string $targetVersion
    ): void {
        foreach ($this->migrations as $migration) {
            $migrationVersion = $migration->version();

            if (
                $installedVersion !== null
                && version_compare(
                    $migrationVersion,
                    $installedVersion,
                    '<='
                )
            ) {
                continue;
            }

            if (
                version_compare(
                    $migrationVersion,
                    $targetVersion,
                    '>'
                )
            ) {
                continue;
            }

            try {
                $migration->up();
            } catch (Throwable $exception) {
                throw InstallationFailed::migration(
                    $migrationVersion,
                    $exception
                );
            }
        }
    }
}
