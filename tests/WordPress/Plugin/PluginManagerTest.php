<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Plugin;

use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Contracts\PluginInterface;
use WPShop\WordPress\Plugin\PluginManager;

final class PluginManagerTest extends TestCase
{
    public function testRegistersAndBootsPlugin(): void
    {
        $plugin = new LifecyclePlugin();
        $manager = new PluginManager();

        $manager->register($plugin);

        self::assertTrue($plugin->registered);
        self::assertFalse($plugin->booted);
        self::assertTrue($manager->has(LifecyclePlugin::class));
        self::assertSame([$plugin], $manager->all());
        self::assertSame(1, $manager->count());

        $manager->boot();

        self::assertTrue($plugin->booted);
        self::assertTrue($manager->isBooted());
    }

    public function testBootIsIdempotent(): void
    {
        $plugin = new LifecyclePlugin();
        $manager = new PluginManager();
        $manager->register($plugin);

        $manager->boot();
        $manager->boot();

        self::assertSame(1, $plugin->bootCalls);
    }

    public function testRejectsDuplicatePluginClass(): void
    {
        $manager = new PluginManager();
        $manager->register(new LifecyclePlugin());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already registered');

        $manager->register(new LifecyclePlugin());
    }

    public function testRejectsRegistrationAfterBoot(): void
    {
        $manager = new PluginManager();
        $manager->boot();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('after boot');

        $manager->register(new LifecyclePlugin());
    }
}

final class LifecyclePlugin implements PluginInterface
{
    public bool $registered = false;

    public bool $booted = false;

    public int $bootCalls = 0;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
        ++$this->bootCalls;
    }
}
