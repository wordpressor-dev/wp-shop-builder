<?php

declare(strict_types=1);

namespace WPShop\Core\Kernel;

use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ModuleInterface;
use WPShop\Core\Module\ModuleRegistry;

final class Kernel implements KernelInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly ModuleRegistry $modules = new ModuleRegistry()
    ) {
    }

    public function register(ModuleInterface $module): void
    {
        $this->modules->register($module);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->modules->bootAll();
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
}