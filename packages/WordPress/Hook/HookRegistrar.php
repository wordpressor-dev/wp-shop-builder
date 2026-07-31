<?php

declare(strict_types=1);

namespace WPShop\WordPress\Hook;

use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Contracts\HookRegistrarInterface;

final class HookRegistrar implements HookRegistrarInterface
{
    public function __construct(
        private readonly HookAdapterInterface $adapter,
        private readonly HookResolver $resolver
    ) {
    }

    public function action(
        string $hook,
        callable|string $handler,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->adapter->addAction(
            $hook,
            $this->resolver->resolve($handler),
            $priority,
            $acceptedArgs
        );
    }

    public function filter(
        string $hook,
        callable|string $handler,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->adapter->addFilter(
            $hook,
            $this->resolver->resolve($handler),
            $priority,
            $acceptedArgs
        );
    }
}
