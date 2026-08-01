<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use Closure;
use DateTimeImmutable;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Contracts\ReleaseServiceInterface;
use WPShop\Release\Service\ReleaseService;

final class ReleaseServiceProvider extends
    AbstractServiceProvider
{
    /**
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        ContainerInterface $container,
        private readonly DatabaseConnectionInterface $database,
        private readonly string $table,
        private readonly Closure $clock
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $mapper = new ReleaseRowMapper();

        $repository = new WordPressReleaseRepository(
            $this->database,
            $mapper,
            $this->table,
            $this->clock
        );

        $service = new ReleaseService($repository);

        if (
            ! $this->container->has(
                DatabaseConnectionInterface::class
            )
        ) {
            $this->container->set(
                DatabaseConnectionInterface::class,
                $this->database
            );
        }

        $this->container->set(
            ReleaseRowMapper::class,
            $mapper
        );

        $this->container->set(
            ReleaseRepositoryInterface::class,
            $repository
        );

        $this->container->set(
            WordPressReleaseRepository::class,
            $repository
        );

        $this->container->set(
            ReleaseServiceInterface::class,
            $service
        );

        $this->container->set(
            ReleaseService::class,
            $service
        );
    }
}
