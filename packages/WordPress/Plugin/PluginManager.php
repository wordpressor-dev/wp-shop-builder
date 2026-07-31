<?php

declare(strict_types=1);

namespace WPShop\WordPress\Plugin;

use LogicException;
use WPShop\WordPress\Contracts\PluginInterface;

final class PluginManager
{
    /**
     * @var array<class-string<PluginInterface>, PluginInterface>
     */
    private array $plugins = [];

    private bool $booted = false;

    public function register(PluginInterface $plugin): void
    {
        if ($this->booted) {
            throw new LogicException('Plugins cannot be registered after boot.');
        }

        $pluginClass = $plugin::class;

        if ($this->has($pluginClass)) {
            throw new LogicException(sprintf('Plugin "%s" is already registered.', $pluginClass));
        }

        $plugin->register();
        $this->plugins[$pluginClass] = $plugin;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->plugins as $plugin) {
            $plugin->boot();
        }

        $this->booted = true;
    }

    /**
     * @param class-string<PluginInterface> $pluginClass
     */
    public function has(string $pluginClass): bool
    {
        return isset($this->plugins[$pluginClass]);
    }

    /**
     * @return list<PluginInterface>
     */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    public function count(): int
    {
        return count($this->plugins);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
