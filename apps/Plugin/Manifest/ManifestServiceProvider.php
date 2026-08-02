<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Manifest;

use Closure;
use DateTimeImmutable;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Manifest\Service\ManifestService;

final class ManifestServiceProvider extends
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
        $mapper = new ManifestRowMapper();

        $repository = new WordPressManifestRepository(
            $this->database,
            $mapper,
            $this->table,
            $this->clock
        );

        $service = new ManifestService($repository);

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
            ManifestRowMapper::class,
            $mapper
        );

        $this->container->set(
            ManifestRepositoryInterface::class,
            $repository
        );

        $this->container->set(
            WordPressManifestRepository::class,
            $repository
        );

        $this->container->set(
            ManifestServiceInterface::class,
            $service
        );

        $this->container->set(
            ManifestService::class,
            $service
        );
    }
}
