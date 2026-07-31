<?php

declare(strict_types=1);

namespace WPShop\WordPress\Contracts;

interface HookAdapterInterface
{
    public function addAction(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void;

    public function addFilter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void;
}
