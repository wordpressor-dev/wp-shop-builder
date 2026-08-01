<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Blueprint\Service\BlueprintService;
use WPShop\Core\Container\Container;

final class BlueprintServiceProviderTest extends TestCase
{
    public function testRegistersBlueprintServices(): void
    {
        $container = new Container();
        $database = new ProviderDatabaseConnection();

        $provider = new BlueprintServiceProvider(
            $container,
            $database,
            'wp_wps_blueprints',
            static fn (): string =>
                '123e4567-e89b-12d3-a456-426614174000',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-07-31 10:00:00'
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
            BlueprintRowMapper::class,
            $container->get(
                BlueprintRowMapper::class
            )
        );

        $repository = $container->get(
            BlueprintRepositoryInterface::class
        );

        self::assertInstanceOf(
            WordPressBlueprintRepository::class,
            $repository
        );

        self::assertSame(
            $repository,
            $container->get(
                WordPressBlueprintRepository::class
            )
        );

        $service = $container->get(
            BlueprintServiceInterface::class
        );

        self::assertInstanceOf(
            BlueprintService::class,
            $service
        );

        self::assertSame(
            $service,
            $container->get(
                BlueprintService::class
            )
        );
    }
}

final class ProviderDatabaseConnection implements
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
}
