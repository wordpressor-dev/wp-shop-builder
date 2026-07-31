<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\WordPress\Adapter\TestingHookAdapter;
use WPShop\WordPress\Admin\AdminManager;
use WPShop\WordPress\Admin\AdminMenu;
use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;
use WPShop\WordPress\Admin\DashboardPage;
use WPShop\WordPress\Admin\Provider\AdminServiceProvider;
use WPShop\WordPress\Admin\WordPressAdminApi;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Hook\HookRegistrar;
use WPShop\WordPress\Hook\HookResolver;
use WPShop\WordPress\Plugin\PluginManager;

final class AdminServiceProviderTest extends TestCase
{
    public function testRegistersAdminServicesAndAdminMenuHook(): void
    {
        $container = new Container();
        $adapter = new TestingHookAdapter();
        $hooks = new HookRegistrar($adapter, new HookResolver($container));
        $application = new Application(
            $container,
            new Kernel(),
            new PluginManager()
        );
        $provider = new AdminServiceProvider(
            $container,
            $hooks,
            $application
        );

        $provider->register();

        self::assertFalse($adapter->hasAction('admin_menu'));

        $provider->boot(new Kernel());

        self::assertInstanceOf(
            WordPressAdminApi::class,
            $container->get(AdminApiInterface::class)
        );
        self::assertSame(
            $container->get(AdminApiInterface::class),
            $container->get(WordPressAdminApi::class)
        );
        self::assertInstanceOf(AdminMenu::class, $container->get(AdminMenu::class));
        self::assertInstanceOf(AdminManager::class, $container->get(AdminManager::class));
        self::assertInstanceOf(DashboardPage::class, $container->get(DashboardPage::class));
        self::assertSame(
            $container->get(DashboardPage::class),
            $container->get(AdminPageInterface::class)
        );
        self::assertTrue($adapter->hasAction('admin_menu'));
        self::assertSame(0, $adapter->actions('admin_menu')[0]['accepted_args']);
    }
}
