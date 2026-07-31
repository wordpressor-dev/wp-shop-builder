<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\Environment\Contracts\PhpEnvironmentInterface;
use WPShop\Environment\Contracts\ServerEnvironmentInterface;
use WPShop\Environment\Contracts\WordPressEnvironmentInterface;
use WPShop\Environment\Provider\EnvironmentServiceProvider;
use WPShop\System\Contracts\SystemServiceInterface;
use WPShop\System\Provider\SystemServiceProvider;
use WPShop\Version\Contracts\VersionServiceInterface;
use WPShop\Version\Provider\VersionServiceProvider;
use WPShop\WordPress\Adapter\NativeHookAdapter;
use WPShop\WordPress\Admin\AdminManager;
use WPShop\WordPress\Admin\DashboardPage;
use WPShop\WordPress\Admin\Provider\AdminServiceProvider;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Contracts\HookRegistrarInterface;
use WPShop\WordPress\Hook\HookRegistrar;
use WPShop\WordPress\Hook\HookResolver;
use WPShop\WordPress\Plugin\PluginManager;
use WPShop\WordPress\Provider\WordPressServiceProvider;

final class WordPressServiceProviderTest extends TestCase
{
    public function testRegistersWordPressLifecycleServices(): void
    {
        $container = new Container();
        $plugins = new PluginManager();
        $application = new Application($container, new Kernel(), $plugins);
        $provider = new WordPressServiceProvider(
            $container,
            $application,
            $plugins
        );

        $provider->register();

        self::assertSame($application, $container->get(Application::class));
        self::assertSame($plugins, $container->get(PluginManager::class));
        self::assertInstanceOf(
            NativeHookAdapter::class,
            $container->get(HookAdapterInterface::class)
        );
        self::assertInstanceOf(
            HookResolver::class,
            $container->get(HookResolver::class)
        );
        self::assertInstanceOf(
            HookRegistrar::class,
            $container->get(HookRegistrarInterface::class)
        );
        self::assertInstanceOf(
            EnvironmentServiceProvider::class,
            $container->get(EnvironmentServiceProvider::class)
        );
        self::assertInstanceOf(
            PhpEnvironmentInterface::class,
            $container->get(PhpEnvironmentInterface::class)
        );
        self::assertInstanceOf(
            ServerEnvironmentInterface::class,
            $container->get(ServerEnvironmentInterface::class)
        );
        self::assertInstanceOf(
            WordPressEnvironmentInterface::class,
            $container->get(WordPressEnvironmentInterface::class)
        );
        self::assertInstanceOf(
            VersionServiceProvider::class,
            $container->get(VersionServiceProvider::class)
        );
        self::assertInstanceOf(
            VersionServiceInterface::class,
            $container->get(VersionServiceInterface::class)
        );
        self::assertInstanceOf(
            SystemServiceProvider::class,
            $container->get(SystemServiceProvider::class)
        );
        self::assertInstanceOf(
            SystemServiceInterface::class,
            $container->get(SystemServiceInterface::class)
        );
        self::assertInstanceOf(
            AdminServiceProvider::class,
            $container->get(AdminServiceProvider::class)
        );
        self::assertInstanceOf(
            AdminManager::class,
            $container->get(AdminManager::class)
        );
        self::assertInstanceOf(
            DashboardPage::class,
            $container->get(DashboardPage::class)
        );
    }
}
