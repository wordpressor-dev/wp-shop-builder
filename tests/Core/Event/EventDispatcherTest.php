<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Event;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Event\EventDispatcher;

final class EventDispatcherTest extends TestCase
{
    public function testDispatchReturnsSameEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new TestEvent('product-created');

        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    public function testListenerReceivesDispatchedEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new TestEvent('product-created');

        $receivedEvent = null;

        $dispatcher->listen(
            TestEvent::class,
            static function (TestEvent $event) use (&$receivedEvent): void {
                $receivedEvent = $event;
            }
        );

        $dispatcher->dispatch($event);

        self::assertSame($event, $receivedEvent);
    }

    public function testMultipleListenersAreExecutedInRegistrationOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $executionOrder = [];

        $dispatcher->listen(
            TestEvent::class,
            static function () use (&$executionOrder): void {
                $executionOrder[] = 'first';
            }
        );

        $dispatcher->listen(
            TestEvent::class,
            static function () use (&$executionOrder): void {
                $executionOrder[] = 'second';
            }
        );

        $dispatcher->dispatch(new TestEvent('product-created'));

        self::assertSame(
            ['first', 'second'],
            $executionOrder
        );
    }

    public function testListenerForDifferentEventIsNotExecuted(): void
    {
        $dispatcher = new EventDispatcher();
        $listenerExecuted = false;

        $dispatcher->listen(
            AnotherTestEvent::class,
            static function () use (&$listenerExecuted): void {
                $listenerExecuted = true;
            }
        );

        $dispatcher->dispatch(new TestEvent('product-created'));

        self::assertFalse($listenerExecuted);
    }

    public function testEventCanBeDispatchedWithoutListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $event = new TestEvent('product-created');

        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }
}

final class TestEvent
{
    public function __construct(
        public readonly string $name
    ) {
    }
}

final class AnotherTestEvent
{
}
