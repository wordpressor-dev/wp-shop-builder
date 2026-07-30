<?php

declare(strict_types=1);

namespace WPShop\Core\Provider;

use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;

abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        protected readonly ContainerInterface $container
    ) {
    }

    public function boot(KernelInterface $kernel): void
    {
    }
}
