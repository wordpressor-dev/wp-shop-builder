<?php

declare(strict_types=1);

namespace WPShop\Core\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    public function set(string $id, mixed $service): void;

    /**
     * Register a lazily resolved shared service.
     *
     * @param Closure(self): mixed $resolver
     */
    public function factory(string $id, Closure $resolver): void;

    /**
     * Resolve and instantiate a class using constructor injection.
     */
    public function autowire(string $id): object;
}
