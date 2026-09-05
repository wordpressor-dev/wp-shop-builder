<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use LogicException;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialSelectionGuard;
use WPShop\App\Plugin\ProductManager\ProductManagerServiceProvider;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\ProviderRegistryInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\WordPress\Bootstrap\Bootstrap as WordPressBootstrap;

final readonly class Plugin
{
    public const VERSION = '0.3.0';

    /**
     * @param null|Closure(
     *     ContainerInterface
     * ): ServiceProviderInterface $serviceProviderFactory
     */
    public function __construct(
        private ?Closure $serviceProviderFactory = null
    ) {
    }

    public function boot(): void
    {
        $application = WordPressBootstrap::create();

        if ($this->serviceProviderFactory !== null) {
            $provider = ($this->serviceProviderFactory)(
                $application->container()
            );

            $kernel = $application->kernel();

            if (
                ! $kernel instanceof
                ProviderRegistryInterface
            ) {
                throw new LogicException(
                    'Application kernel does not support service providers.'
                );
            }

            $kernel->addProvider($provider);
        }

        $application->boot();

        /*
         * WordPressServiceProvider has now registered the admin registry,
         * while the admin_menu hook has not fired yet. Register the
         * application-specific Product Manager module into that registry.
         */
        $productManagerProvider =
            new ProductManagerServiceProvider(
                $application->container()
            );

        $productManagerProvider->register();
        $productManagerProvider->boot(
            $application->kernel()
        );

        $editorialMigration = $application->container()->get(
            ProductEditorialMigrationService::class
        );
        $functionCaller = $application->container()->get(
            WordPressFunctionCaller::class
        );

        if (! $editorialMigration instanceof ProductEditorialMigrationService) {
            throw new LogicException(
                'ProductEditorialMigrationService must be registered before selection guard.'
            );
        }

        if (! $functionCaller instanceof WordPressFunctionCaller) {
            throw new LogicException(
                'WordPressFunctionCaller must be registered before selection guard.'
            );
        }

        $selectionGuard = new ProductEditorialSelectionGuard(
            $editorialMigration,
            $functionCaller(...)
        );
        $selectionGuard->register();
    }
}
