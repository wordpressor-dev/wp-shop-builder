<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin;

use Closure;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Database\WordPressTransactionManager;
use WPShop\App\Plugin\Manifest\ManifestServiceProvider;
use WPShop\App\Plugin\Manifest\WordPressManifestRepository;
use WPShop\App\Plugin\PluginServiceProvider;
use WPShop\App\Plugin\Release\ReleasePublicationService;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\App\Plugin\Release\WordPressReleaseRepository;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Contracts\ReleaseServiceInterface;

final class PluginServiceProviderTest extends TestCase
{
    public function testRegistersPluginDomainServices(): void
    {
        $container = new Container();

        $database =
            new PluginProviderDatabaseConnection();

        $provider = $this->provider(
            $container,
            $database
        );

        $provider->register();

        self::assertSame(
            $database,
            $container->get(
                DatabaseConnectionInterface::class
            )
        );

        self::assertInstanceOf(
            BlueprintServiceProvider::class,
            $container->get(
                BlueprintServiceProvider::class
            )
        );

        self::assertInstanceOf(
            ReleaseServiceProvider::class,
            $container->get(
                ReleaseServiceProvider::class
            )
        );

        self::assertInstanceOf(
            ManifestServiceProvider::class,
            $container->get(
                ManifestServiceProvider::class
            )
        );

        self::assertInstanceOf(
            BlueprintServiceInterface::class,
            $container->get(
                BlueprintServiceInterface::class
            )
        );

        self::assertInstanceOf(
            ReleaseServiceInterface::class,
            $container->get(
                ReleaseServiceInterface::class
            )
        );

        self::assertInstanceOf(
            ManifestServiceInterface::class,
            $container->get(
                ManifestServiceInterface::class
            )
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressBlueprintRepository::class
            ),
            $database,
            'wp_wps_blueprints'
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressReleaseRepository::class
            ),
            $database,
            'wp_wps_releases'
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressManifestRepository::class
            ),
            $database,
            'wp_wps_manifests'
        );

        $provider->boot(new Kernel());
    }

    public function testRegistersPublicationWorkflowServices(): void
    {
        $container = new Container();

        $database =
            new PluginProviderDatabaseConnection();

        /** @var list<string> $queries */
        $queries = [];

        $query = static function (
            string $sql
        ) use (&$queries): int {
            $queries[] = $sql;

            return 0;
        };

        $provider = $this->provider(
            $container,
            $database,
            $query
        );

        $provider->register();

        $transactionManager = $container->get(
            TransactionManagerInterface::class
        );

        if (
            ! $transactionManager instanceof
                WordPressTransactionManager
        ) {
            self::fail(
                'WordPress transaction manager was not registered.'
            );
        }

        self::assertSame(
            $transactionManager,
            $container->get(
                WordPressTransactionManager::class
            )
        );

        self::assertSame(
            'publication-result',
            $transactionManager->transactional(
                static fn(): string =>
                    'publication-result'
            )
        );

        self::assertSame(
            [
                'START TRANSACTION',
                'COMMIT',
            ],
            $queries
        );

        $publicationService = $container->get(
            ReleasePublicationServiceInterface::class
        );

        if (
            ! $publicationService instanceof
                ReleasePublicationService
        ) {
            self::fail(
                'Release publication service was not registered.'
            );
        }

        self::assertSame(
            $publicationService,
            $container->get(
                ReleasePublicationService::class
            )
        );

        self::assertSame(
            $container->get(
                ReleaseRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'releaseRepository'
            )
        );

        self::assertSame(
            $container->get(
                BlueprintRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'blueprintRepository'
            )
        );

        self::assertSame(
            $container->get(
                ManifestRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'manifestRepository'
            )
        );

        self::assertSame(
            $transactionManager,
            $this->property(
                $publicationService,
                'transactionManager'
            )
        );
    }

    public function testBootRequiresRegistration(): void
    {
        $container = new Container();

        $provider = $this->provider(
            $container,
            new PluginProviderDatabaseConnection()
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'PluginServiceProvider must be registered before it is booted.'
        );

        $provider->boot(new Kernel());
    }

    private function provider(
        Container $container,
        PluginProviderDatabaseConnection $database,
        ?Closure $query = null
    ): PluginServiceProvider {
        return new PluginServiceProvider(
            $container,
            $database,
            'wp_wps_blueprints',
            'wp_wps_releases',
            'wp_wps_manifests',
            static fn(): string =>
                '123e4567-e89b-12d3-a456-426614174000',
            static fn(): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 12:00:00'
                ),
            $query ?? static fn(string $sql): int => 0
        );
    }

    private function assertRepositoryConfiguration(
        object $repository,
        PluginProviderDatabaseConnection $database,
        string $table
    ): void {
        self::assertSame(
            $database,
            $this->property(
                $repository,
                'database'
            )
        );

        self::assertSame(
            $table,
            $this->property(
                $repository,
                'table'
            )
        );
    }

    private function property(
        object $object,
        string $name
    ): mixed {
        $property = new ReflectionProperty(
            $object,
            $name
        );

        return $property->getValue($object);
    }
}

final class PluginProviderDatabaseConnection implements
    DatabaseConnectionInterface
{
    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        return 1;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        return 0;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        return null;
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        return [];
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
