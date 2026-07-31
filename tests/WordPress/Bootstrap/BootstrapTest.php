<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Bootstrap;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Bootstrap\Bootstrap;
use WPShop\WordPress\Adapter\TestingHookAdapter;
use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Plugin\PluginManager;

final class BootstrapTest extends TestCase
{
    public function testCreatesConfiguredApplication(): void
    {
        $application = Bootstrap::create();

        self::assertInstanceOf(Application::class, $application);
        self::assertFalse($application->isBooted());
        self::assertSame(
            $application->container(),
            $application->container()->get(ContainerInterface::class)
        );
        self::assertSame(
            $application->kernel(),
            $application->container()->get(KernelInterface::class)
        );
    }

    public function testRunBootsApplicationAndRegistersProviderServices(): void
    {
        $application = Bootstrap::run(new TestingHookAdapter());
        $container = $application->container();

        self::assertTrue($application->isBooted());
        self::assertSame($application, $container->get(Application::class));
        self::assertSame(
            $application->plugins(),
            $container->get(PluginManager::class)
        );
        self::assertInstanceOf(
            HookAdapterInterface::class,
            $container->get(HookAdapterInterface::class)
        );
    }
}
