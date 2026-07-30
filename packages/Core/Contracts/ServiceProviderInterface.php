<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

interface ServiceProviderInterface
{
    /**
     * Register services and bindings required by the provider.
     */
    public function register(): void;

    /**
     * Perform final initialization after all providers are registered
     * and all modules have been booted.
     */
    public function boot(KernelInterface $kernel): void;
}
