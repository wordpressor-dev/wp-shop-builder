<?php

declare(strict_types=1);

namespace WPShop\WordPress\Provider;

use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Environment\Provider\EnvironmentServiceProvider;
use WPShop\System\Provider\SystemServiceProvider;
use WPShop\Version\Provider\VersionServiceProvider;
use WPShop\WordPress\Adapter\NativeHookAdapter;
use WPShop\WordPress\Admin\Provider\AdminServiceProvider;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Contracts\HookRegistrarInterface;
use WPShop\WordPress\Hook\HookRegistrar;
use WPShop\WordPress\Hook\HookResolver;
use WPShop\WordPress\Plugin\PluginManager;

final class WordPressServiceProvider extends AbstractServiceProvider
{
    private ?AdminServiceProvider $adminProvider = null;

    private ?EnvironmentServiceProvider $environmentProvider = null;

    private ?VersionServiceProvider $versionProvider = null;

    private ?SystemServiceProvider $systemProvider = null;

    public function __construct(
        ContainerInterface $container,
        private readonly Application $application,
        private readonly PluginManager $plugins,
        private readonly ?HookAdapterInterface $hookAdapter = null
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $this->container->set(Application::class, $this->application);
        $this->container->set(PluginManager::class, $this->plugins);
        $hookAdapter = $this->hookAdapter ?? new NativeHookAdapter();
        $hookResolver = new HookResolver($this->container);
        $hookRegistrar = new HookRegistrar($hookAdapter, $hookResolver);

        $this->container->set(HookAdapterInterface::class, $hookAdapter);
        $this->container->set(HookResolver::class, $hookResolver);
        $this->container->set(HookRegistrarInterface::class, $hookRegistrar);

        $this->environmentProvider = new EnvironmentServiceProvider($this->container);
        $this->environmentProvider->register();
        $this->container->set(
            EnvironmentServiceProvider::class,
            $this->environmentProvider
        );

        $this->versionProvider = new VersionServiceProvider($this->container);
        $this->versionProvider->register();
        $this->container->set(
            VersionServiceProvider::class,
            $this->versionProvider
        );

        $this->systemProvider = new SystemServiceProvider($this->container);
        $this->systemProvider->register();
        $this->container->set(
            SystemServiceProvider::class,
            $this->systemProvider
        );

        $this->adminProvider = new AdminServiceProvider(
            $this->container,
            $hookRegistrar,
            $this->application
        );
        $this->adminProvider->register();
        $this->container->set(AdminServiceProvider::class, $this->adminProvider);
    }

    public function boot(KernelInterface $kernel): void
    {
        if ($this->adminProvider === null) {
            throw new \LogicException('WordPressServiceProvider must be registered before it is booted.');
        }

        $this->adminProvider->boot($kernel);
    }
}
