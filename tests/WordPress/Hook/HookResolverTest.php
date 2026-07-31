<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Hook;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\WordPress\Exception\InvalidHookHandler;
use WPShop\WordPress\Hook\HookResolver;

final class HookResolverTest extends TestCase
{
    public function testReturnsCallableWithoutUsingContainer(): void
    {
        $resolver = new HookResolver(new Container());
        $callback = static fn (string $value): string => strtoupper($value);

        self::assertSame($callback, $resolver->resolve($callback));
    }

    public function testResolvesInvokableServiceFromContainer(): void
    {
        $container = new Container();
        $listener = new ResolverInvokableListener();
        $container->set(ResolverInvokableListener::class, $listener);

        $resolver = new HookResolver($container);

        self::assertSame(
            $listener,
            $resolver->resolve(ResolverInvokableListener::class)
        );
    }

    public function testRejectsNonCallableService(): void
    {
        $container = new Container();
        $container->set(ResolverNonCallableService::class, new ResolverNonCallableService());

        $resolver = new HookResolver($container);

        $this->expectException(InvalidHookHandler::class);
        $this->expectExceptionMessage('must be callable');

        $resolver->resolve(ResolverNonCallableService::class);
    }
}

final class ResolverInvokableListener
{
    public function __invoke(): void
    {
    }
}

final class ResolverNonCallableService
{
}
