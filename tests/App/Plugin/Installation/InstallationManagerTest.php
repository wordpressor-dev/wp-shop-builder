<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Installation;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Installation\Contracts\InstalledVersionStoreInterface;
use WPShop\App\Plugin\Installation\Contracts\MigrationInterface;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;
use WPShop\App\Plugin\Installation\InstallationManager;
use WPShop\App\Plugin\Installation\MigrationRunner;

final class InstallationManagerTest extends TestCase
{
    public function testFirstInstallationRunsMigrationsAndSavesVersion(): void
    {
        $calls = [];
        $store = new ManagerInstalledVersionStore(null);

        $manager = new InstallationManager(
            $store,
            new MigrationRunner([
                new ManagerRecordingMigration(
                    '0.1.0',
                    static function () use (&$calls): void {
                        $calls[] = '0.1.0';
                    }
                ),
                new ManagerRecordingMigration(
                    '0.2.0',
                    static function () use (&$calls): void {
                        $calls[] = '0.2.0';
                    }
                ),
            ]),
            '0.2.0'
        );

        $manager->synchronize();

        self::assertSame(
            ['0.1.0', '0.2.0'],
            $calls
        );

        self::assertSame('0.2.0', $store->version);
        self::assertSame(1, $store->writes);
    }

    public function testUpdateRunsOnlyPendingMigrations(): void
    {
        $calls = [];
        $store = new ManagerInstalledVersionStore('0.1.0');

        $manager = new InstallationManager(
            $store,
            new MigrationRunner([
                new ManagerRecordingMigration(
                    '0.1.0',
                    static function () use (&$calls): void {
                        $calls[] = '0.1.0';
                    }
                ),
                new ManagerRecordingMigration(
                    '0.2.0',
                    static function () use (&$calls): void {
                        $calls[] = '0.2.0';
                    }
                ),
                new ManagerRecordingMigration(
                    '0.3.0',
                    static function () use (&$calls): void {
                        $calls[] = '0.3.0';
                    }
                ),
            ]),
            '0.3.0'
        );

        $manager->synchronize();

        self::assertSame(
            ['0.2.0', '0.3.0'],
            $calls
        );

        self::assertSame('0.3.0', $store->version);
        self::assertSame(1, $store->writes);
    }

    public function testCurrentInstallationDoesNothing(): void
    {
        $store = new ManagerInstalledVersionStore('0.3.0');

        $manager = new InstallationManager(
            $store,
            new MigrationRunner([
                new ManagerRecordingMigration(
                    '0.3.0',
                    static function (): void {
                        self::fail(
                            'Migration must not be executed.'
                        );
                    }
                ),
            ]),
            '0.3.0'
        );

        $manager->synchronize();

        self::assertSame('0.3.0', $store->version);
        self::assertSame(0, $store->writes);
    }

    public function testFailedMigrationDoesNotSaveTargetVersion(): void
    {
        $store = new ManagerInstalledVersionStore('0.1.0');

        $manager = new InstallationManager(
            $store,
            new MigrationRunner([
                new ManagerRecordingMigration(
                    '0.2.0',
                    static function (): void {
                        throw new RuntimeException(
                            'Migration failed.'
                        );
                    }
                ),
            ]),
            '0.2.0'
        );

        try {
            $manager->synchronize();

            self::fail(
                'Expected installation failure was not thrown.'
            );
        } catch (InstallationFailed) {
            self::assertSame('0.1.0', $store->version);
            self::assertSame(0, $store->writes);
        }
    }
}

final class ManagerInstalledVersionStore implements
    InstalledVersionStoreInterface
{
    public int $writes = 0;

    public function __construct(
        public ?string $version
    ) {
    }

    public function installedVersion(): ?string
    {
        return $this->version;
    }

    public function saveInstalledVersion(string $version): void
    {
        $this->version = $version;
        $this->writes++;
    }
}

final readonly class ManagerRecordingMigration implements
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
