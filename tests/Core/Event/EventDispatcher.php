<?php

declare(strict_types=1);

namespace WPShop\Core\Event;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<class-string, list<callable(object): void>>
     */
    private array $listeners = [];

    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        return $event;
    }
}
