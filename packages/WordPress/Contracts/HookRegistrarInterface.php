<?php

declare(strict_types=1);

namespace WPShop\WordPress\Contracts;

interface HookRegistrarInterface
{
    /**
     * Register an action callback or an invokable service class.
     *
     * @param callable|string $handler
     */
    public function action(
        string $hook,
        callable|string $handler,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void;

    /**
     * Register a filter callback or an invokable service class.
     *
     * @param callable|string $handler
     */
    public function filter(
        string $hook,
        callable|string $handler,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void;
}
