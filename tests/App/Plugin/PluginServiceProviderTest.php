<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\PluginServiceProvider;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
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

        $provider->boot(new Kernel());
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
        PluginProviderDatabaseConnection $database
    ): PluginServiceProvider {
        return new PluginServiceProvider(
            $container,
            $database,
            'wp_wps_blueprints',
            'wp_wps_releases',
            static fn (): string =>
                '123e4567-e89b-12d3-a456-426614174000',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 12:00:00'
                )
        );
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
