<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Installation;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Installation\Contracts\MigrationInterface;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\MigrationRunner;

final class MigrationRunnerTest extends TestCase
{
    public function testRunsPendingMigrationsInVersionOrder(): void
    {
        $calls = [];

        $runner = new MigrationRunner([
            new RunnerRecordingMigration(
                '0.3.0',
                static function () use (&$calls): void {
                    $calls[] = '0.3.0';
                }
            ),
            new RunnerRecordingMigration(
                '0.1.0',
                static function () use (&$calls): void {
                    $calls[] = '0.1.0';
                }
            ),
            new RunnerRecordingMigration(
                '0.2.0',
                static function () use (&$calls): void {
                    $calls[] = '0.2.0';
                }
            ),
        ]);

        $runner->run('0.1.0', '0.3.0');

        self::assertSame(
            ['0.2.0', '0.3.0'],
            $calls
        );
    }

    public function testDoesNotRunMigrationsAboveTargetVersion(): void
    {
        $calls = [];

        $runner = new MigrationRunner([
            new RunnerRecordingMigration(
                '0.1.0',
                static function () use (&$calls): void {
                    $calls[] = '0.1.0';
                }
            ),
            new RunnerRecordingMigration(
                '0.2.0',
                static function () use (&$calls): void {
                    $calls[] = '0.2.0';
                }
            ),
            new RunnerRecordingMigration(
                '0.3.0',
                static function () use (&$calls): void {
                    $calls[] = '0.3.0';
                }
            ),
        ]);

        $runner->run(null, '0.2.0');

        self::assertSame(
            ['0.1.0', '0.2.0'],
            $calls
        );
    }

    public function testRejectsDuplicateMigrationVersions(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Duplicate migration version: 0.1.0.'
        );

        new MigrationRunner([
            new RunnerRecordingMigration(
                '0.1.0',
                static function (): void {
                }
            ),
            new RunnerRecordingMigration(
                '0.1.0',
                static function (): void {
                }
            ),
        ]);
    }

    public function testWrapsMigrationFailure(): void
    {
        $previous = new RuntimeException(
            'Database operation failed.'
        );

        $runner = new MigrationRunner([
            new RunnerRecordingMigration(
                '0.2.0',
                static function () use ($previous): void {
                    throw $previous;
                }
            ),
        ]);

        try {
            $runner->run('0.1.0', '0.2.0');

            self::fail(
                'Expected migration failure was not thrown.'
            );
        } catch (InstallationFailed $exception) {
            self::assertSame(
                'Migration for version 0.2.0 failed: '
                . 'Database operation failed.',
                $exception->getMessage()
            );

            self::assertSame(
                $previous,
                $exception->getPrevious()
            );
        }
    }
}

final readonly class RunnerRecordingMigration implements
    MigrationInterface
{
    public function __construct(
        private string $migrationVersion,
        private Closure $callback
    ) {
    }

    public function version(): string
    {
        return $this->migrationVersion;
    }

    public function up(): void
    {
        ($this->callback)();
    }
}
