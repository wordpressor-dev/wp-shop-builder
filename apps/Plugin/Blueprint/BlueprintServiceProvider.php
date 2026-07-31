<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Blueprint;

use Closure;
use DateTimeImmutable;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Blueprint\Service\BlueprintService;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Provider\AbstractServiceProvider;

final class BlueprintServiceProvider extends
    AbstractServiceProvider
{
    /**
     * @param Closure(): string $uuidGenerator
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        ContainerInterface $container,
        private readonly DatabaseConnectionInterface $database,
        private readonly string $table,
        private readonly Closure $uuidGenerator,
        private readonly Closure $clock
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $mapper = new BlueprintRowMapper();

        $repository = new WordPressBlueprintRepository(
            $this->database,
            $mapper,
            $this->table,
            $this->uuidGenerator,
            $this->clock
        );

        $service = new BlueprintService($repository);

        $this->container->set(
            DatabaseConnectionInterface::class,
            $this->database
        );

        $this->container->set(
            BlueprintRowMapper::class,
            $mapper
        );

        $this->container->set(
            BlueprintRepositoryInterface::class,
            $repository
        );

        $this->container->set(
            WordPressBlueprintRepository::class,
            $repository
        );

        $this->container->set(
            BlueprintServiceInterface::class,
            $service
        );

        $this->container->set(
            BlueprintService::class,
            $service
        );
    }
}
