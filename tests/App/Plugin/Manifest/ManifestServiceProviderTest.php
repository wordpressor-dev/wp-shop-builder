<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Manifest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Manifest\ManifestRowMapper;
use WPShop\App\Plugin\Manifest\ManifestServiceProvider;
use WPShop\App\Plugin\Manifest\WordPressManifestRepository;
use WPShop\Core\Container\Container;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Manifest\Service\ManifestService;

final class ManifestServiceProviderTest extends TestCase
{
    public function testRegistersManifestServices(): void
    {
        $container = new Container();

        $database =
            new ManifestProviderDatabaseConnection();

        $provider = new ManifestServiceProvider(
            $container,
            $database,
            'wp_wps_manifests',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-02 13:00:00'
                )
        );

        $provider->register();

        self::assertSame(
            $database,
            $container->get(
                DatabaseConnectionInterface::class
            )
        );

        self::assertInstanceOf(
            ManifestRowMapper::class,
            $container->get(
                ManifestRowMapper::class
            )
        );

        $repository = $container->get(
            ManifestRepositoryInterface::class
        );

        self::assertInstanceOf(
            WordPressManifestRepository::class,
            $repository
        );

        self::assertSame(
            $repository,
            $container->get(
                WordPressManifestRepository::class
            )
        );

        $service = $container->get(
            ManifestServiceInterface::class
        );

        self::assertInstanceOf(
            ManifestService::class,
            $service
        );

        self::assertSame(
            $service,
            $container->get(
                ManifestService::class
            )
        );
    }

    public function testPreservesExistingDatabaseBinding(): void
    {
        $container = new Container();

        $existingDatabase =
            new ManifestProviderDatabaseConnection();

        $providerDatabase =
            new ManifestProviderDatabaseConnection();

        $container->set(
            DatabaseConnectionInterface::class,
            $existingDatabase
        );

        $provider = new ManifestServiceProvider(
            $container,
            $providerDatabase,
            'wp_wps_manifests',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-02 13:00:00'
                )
        );

        $provider->register();

        self::assertSame(
            $existingDatabase,
            $container->get(
                DatabaseConnectionInterface::class
            )
        );
    }
}

final class ManifestProviderDatabaseConnection implements
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
