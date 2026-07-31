<?php

declare(strict_types=1);

namespace WPShop\WordPress\Bootstrap;

use WPShop\Core\Container\Container;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Kernel\Kernel;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Plugin\PluginManager;
use WPShop\WordPress\Provider\WordPressServiceProvider;

final class Bootstrap
{
    public static function create(?HookAdapterInterface $hookAdapter = null): Application
    {
        $container = new Container();
        $kernel = new Kernel();
        $plugins = new PluginManager();
        $application = new Application($container, $kernel, $plugins);

        $container->set(ContainerInterface::class, $container);
        $container->set(KernelInterface::class, $kernel);

        $kernel->addProvider(
            new WordPressServiceProvider(
                $container,
                $application,
                $plugins,
                $hookAdapter
            )
        );

        return $application;
    }

    public static function run(?HookAdapterInterface $hookAdapter = null): Application
    {
        $application = self::create($hookAdapter);
        $application->boot();

        return $application;
    }
}
