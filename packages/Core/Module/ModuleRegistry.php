<?php

declare(strict_types=1);

namespace WPShop\Core\Module;

use WPShop\Core\Contracts\ModuleInterface;
use WPShop\Core\Exception\ModuleAlreadyRegistered;
use WPShop\Core\Exception\ModuleNotFound;

final class ModuleRegistry
{
    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $id = $module->id();

        if ($this->has($id)) {
            throw ModuleAlreadyRegistered::forId($id);
        }

        $this->modules[$id] = $module;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    public function get(string $id): ModuleInterface
    {
        if (!$this->has($id)) {
            throw ModuleNotFound::forId($id);
        }

        return $this->modules[$id];
    }

    /**
     * @return list<ModuleInterface>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function bootAll(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }
}