<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use LogicException;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\ProviderRegistryInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\WordPress\Bootstrap\Bootstrap as WordPressBootstrap;

final readonly class Plugin
{
    public const VERSION = '0.2.0';

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
    }
}
