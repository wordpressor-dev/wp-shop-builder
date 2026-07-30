<?php

declare(strict_types=1);

namespace WPShop\Core\Event;

interface EventDispatcherInterface
{
    /**
     * @param class-string $eventClass
     * @param callable(object): void $listener
     */
    public function listen(string $eventClass, callable $listener): void;

    public function dispatch(object $event): object;
}
