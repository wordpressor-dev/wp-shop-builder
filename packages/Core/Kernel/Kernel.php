<?php

declare(strict_types=1);

namespace WPShop\Core\Kernel;

use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ModuleInterface;
use WPShop\Core\Contracts\ProviderRegistryInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\Core\Module\ModuleRegistry;
use WPShop\Core\Provider\ServiceProviderRepository;

final class Kernel implements KernelInterface, ProviderRegistryInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly ModuleRegistry $modules = new ModuleRegistry(),
        private readonly ServiceProviderRepository $serviceProviders = new ServiceProviderRepository()
    ) {
    }

    public function register(ModuleInterface $module): void
    {
        $this->modules->register($module);
    }

    public function addProvider(ServiceProviderInterface $provider): void
    {
        $this->serviceProviders->add($provider);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->serviceProviders->registerAll();
        $this->modules->bootAll();
        $this->serviceProviders->bootAll($this);
        $this->booted = true;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function modules(): ModuleRegistry
    {
        return $this->modules;
    }

    public function providers(): ServiceProviderRepository
    {
        return $this->serviceProviders;
    }
}
