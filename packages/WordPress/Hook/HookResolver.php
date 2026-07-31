<?php

declare(strict_types=1);

namespace WPShop\WordPress\Hook;

use WPShop\Core\Container\ContainerInterface;
use WPShop\WordPress\Exception\InvalidHookHandler;

final class HookResolver
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @param callable|string $handler
     */
    public function resolve(callable|string $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        $service = $this->container->get($handler);

        if (!is_callable($service)) {
            throw InvalidHookHandler::forService($handler);
        }

        return $service;
    }
}
