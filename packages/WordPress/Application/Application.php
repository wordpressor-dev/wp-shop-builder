<?php

declare(strict_types=1);

namespace WPShop\WordPress\Application;

use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Framework;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\WordPress\Contracts\PluginInterface;
use WPShop\WordPress\Plugin\PluginManager;

final class Application
{
    public const VERSION = Framework::VERSION;

    private bool $booted = false;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly KernelInterface $kernel,
        private readonly PluginManager $plugins
    ) {
    }

    public function registerPlugin(PluginInterface $plugin): self
    {
        $this->plugins->register($plugin);

        return $this;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->kernel->boot();
        $this->plugins->boot();
        $this->booted = true;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function kernel(): KernelInterface
    {
        return $this->kernel;
    }

    public function plugins(): PluginManager
    {
        return $this->plugins;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
