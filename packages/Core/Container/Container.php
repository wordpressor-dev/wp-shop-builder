<?php

declare(strict_types=1);

namespace WPShop\Core\Container;

use Closure;
use WPShop\Core\Container\Exception\ServiceAlreadyRegistered;
use WPShop\Core\Container\Exception\ServiceNotFound;

final class Container implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    public function set(string $id, mixed $service): void
    {
        $this->assertNotRegistered($id);
        $this->definitions[$id] = $service;
    }

    public function factory(string $id, Closure $resolver): void
    {
        $this->assertNotRegistered($id);
        $this->definitions[$id] = new Factory($resolver);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!array_key_exists($id, $this->definitions)) {
            throw ServiceNotFound::forId($id);
        }

        $definition = $this->definitions[$id];

        if ($definition instanceof Factory) {
            $definition = $definition->resolve($this);
        }

        $this->resolved[$id] = $definition;

        return $definition;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions)
            || array_key_exists($id, $this->resolved);
    }

    private function assertNotRegistered(string $id): void
    {
        if ($this->has($id)) {
            throw ServiceAlreadyRegistered::forId($id);
        }
    }
}
