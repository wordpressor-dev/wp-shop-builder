<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Application;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Contracts\PluginInterface;
use WPShop\WordPress\Plugin\PluginManager;

final class ApplicationTest extends TestCase
{
    public function testExposesApplicationServices(): void
    {
        $container = new Container();
        $kernel = new Kernel();
        $plugins = new PluginManager();
        $application = new Application($container, $kernel, $plugins);

        self::assertSame($container, $application->container());
        self::assertSame($kernel, $application->kernel());
        self::assertSame($plugins, $application->plugins());
        self::assertSame(Application::VERSION, $application->version());
        self::assertFalse($application->isBooted());
    }

    public function testRegistersPluginAndBootsLifecycle(): void
    {
        $application = new Application(
            new Container(),
            new Kernel(),
            new PluginManager()
        );
        $plugin = new ApplicationPlugin();

        self::assertSame($application, $application->registerPlugin($plugin));
        self::assertTrue($plugin->registered);

        $application->boot();

        self::assertTrue($application->kernel()->isBooted());
        self::assertTrue($plugin->booted);
        self::assertTrue($application->isBooted());
    }

    public function testBootIsIdempotent(): void
    {
        $application = new Application(
            new Container(),
            new Kernel(),
            new PluginManager()
        );
        $plugin = new ApplicationPlugin();
        $application->registerPlugin($plugin);

        $application->boot();
        $application->boot();

        self::assertSame(1, $plugin->bootCalls);
    }
}

final class ApplicationPlugin implements PluginInterface
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
