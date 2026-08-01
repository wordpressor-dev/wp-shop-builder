<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use DateTimeImmutable;
use LogicException;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;

final class PluginServiceProvider extends
    AbstractServiceProvider
{
    private ?BlueprintServiceProvider $blueprintProvider =
        null;

    private ?ReleaseServiceProvider $releaseProvider =
        null;

    /**
     * @param Closure(): string $uuidGenerator
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        ContainerInterface $container,
        private readonly DatabaseConnectionInterface $database,
        private readonly string $blueprintsTable,
        private readonly string $releasesTable,
        private readonly Closure $uuidGenerator,
        private readonly Closure $clock
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $this->blueprintProvider =
            new BlueprintServiceProvider(
                $this->container,
                $this->database,
                $this->blueprintsTable,
                $this->uuidGenerator,
                $this->clock
            );

        $this->blueprintProvider->register();

        $this->container->set(
            BlueprintServiceProvider::class,
            $this->blueprintProvider
        );

        $this->releaseProvider =
            new ReleaseServiceProvider(
                $this->container,
                $this->database,
                $this->releasesTable,
                $this->clock
            );

        $this->releaseProvider->register();

        $this->container->set(
            ReleaseServiceProvider::class,
            $this->releaseProvider
        );
    }

    public function boot(KernelInterface $kernel): void
    {
        if (
            $this->blueprintProvider === null
            || $this->releaseProvider === null
        ) {
            throw new LogicException(
                'PluginServiceProvider must be registered before it is booted.'
            );
        }

        $this->blueprintProvider->boot($kernel);
        $this->releaseProvider->boot($kernel);
    }
}
