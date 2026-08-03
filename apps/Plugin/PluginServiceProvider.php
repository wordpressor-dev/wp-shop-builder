<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use DateTimeImmutable;
use LogicException;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Database\WordPressTransactionManager;
use WPShop\App\Plugin\Manifest\ManifestServiceProvider;
use WPShop\App\Plugin\Release\ReleasePublicationService;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;

final class PluginServiceProvider extends AbstractServiceProvider
{
    private ?BlueprintServiceProvider $blueprintProvider =
        null;

    private ?ReleaseServiceProvider $releaseProvider =
        null;

    private ?ManifestServiceProvider $manifestProvider =
        null;

    /**
     * @param Closure(): string $uuidGenerator
     * @param Closure(): DateTimeImmutable $clock
     * @param Closure(string): (int|bool) $query
     */
    public function __construct(
        ContainerInterface $container,
        private readonly DatabaseConnectionInterface $database,
        private readonly string $blueprintsTable,
        private readonly string $releasesTable,
        private readonly string $manifestsTable,
        private readonly Closure $uuidGenerator,
        private readonly Closure $clock,
        private readonly Closure $query
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $transactionManager =
            new WordPressTransactionManager(
                $this->query
            );

        $this->container->set(
            TransactionManagerInterface::class,
            $transactionManager
        );

        $this->container->set(
            WordPressTransactionManager::class,
            $transactionManager
        );

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

        $this->manifestProvider =
            new ManifestServiceProvider(
                $this->container,
                $this->database,
                $this->manifestsTable,
                $this->clock
            );

        $this->manifestProvider->register();

        $this->container->set(
            ManifestServiceProvider::class,
            $this->manifestProvider
        );

        $releaseRepository = $this->container->get(
            ReleaseRepositoryInterface::class
        );

        $blueprintRepository = $this->container->get(
            BlueprintRepositoryInterface::class
        );

        $manifestRepository = $this->container->get(
            ManifestRepositoryInterface::class
        );

        if (
            ! $releaseRepository instanceof
                ReleaseRepositoryInterface
            || ! $blueprintRepository instanceof
                BlueprintRepositoryInterface
            || ! $manifestRepository instanceof
                ManifestRepositoryInterface
        ) {
            throw new LogicException(
                'Plugin domain repositories must be registered '
                . 'before publication services.'
            );
        }

        $publicationService =
            new ReleasePublicationService(
                $releaseRepository,
                $blueprintRepository,
                $manifestRepository,
                $transactionManager
            );

        $this->container->set(
            ReleasePublicationServiceInterface::class,
            $publicationService
        );

        $this->container->set(
            ReleasePublicationService::class,
            $publicationService
        );
    }

    public function boot(KernelInterface $kernel): void
    {
        if (
            !$this->blueprintProvider instanceof \WPShop\App\Plugin\Blueprint\BlueprintServiceProvider
            || !$this->releaseProvider instanceof \WPShop\App\Plugin\Release\ReleaseServiceProvider
            || !$this->manifestProvider instanceof \WPShop\App\Plugin\Manifest\ManifestServiceProvider
        ) {
            throw new LogicException(
                'PluginServiceProvider must be registered before it is booted.'
            );
        }

        $this->blueprintProvider->boot($kernel);
        $this->releaseProvider->boot($kernel);
        $this->manifestProvider->boot($kernel);
    }
}
