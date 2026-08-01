<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Release\ReleaseRowMapper;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\App\Plugin\Release\WordPressReleaseRepository;
use WPShop\Core\Container\Container;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Contracts\ReleaseServiceInterface;
use WPShop\Release\Service\ReleaseService;

final class ReleaseServiceProviderTest extends TestCase
{
    public function testRegistersReleaseServices(): void
    {
        $container = new Container();

        $database =
            new ReleaseProviderDatabaseConnection();

        $provider = new ReleaseServiceProvider(
            $container,
            $database,
            'wp_wps_releases',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 12:00:00'
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
            ReleaseRowMapper::class,
            $container->get(
                ReleaseRowMapper::class
            )
        );

        $repository = $container->get(
            ReleaseRepositoryInterface::class
        );

        self::assertInstanceOf(
            WordPressReleaseRepository::class,
            $repository
        );

        self::assertSame(
            $repository,
            $container->get(
                WordPressReleaseRepository::class
            )
        );

        $service = $container->get(
            ReleaseServiceInterface::class
        );

        self::assertInstanceOf(
            ReleaseService::class,
            $service
        );

        self::assertSame(
            $service,
            $container->get(
                ReleaseService::class
            )
        );
    }
}

final class ReleaseProviderDatabaseConnection implements
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
