<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin\Provider;

use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\WordPress\Admin\AdminManager;
use WPShop\WordPress\Admin\AdminMenu;
use WPShop\WordPress\Admin\AdminPageRegistry;
use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;
use WPShop\WordPress\Admin\DashboardPage;
use WPShop\WordPress\Admin\WordPressAdminApi;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Contracts\HookRegistrarInterface;

final class AdminServiceProvider extends AbstractServiceProvider
{
    private ?AdminManager $manager = null;

    public function __construct(
        ContainerInterface $container,
        private readonly HookRegistrarInterface $hooks,
        private readonly Application $application
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $api = new WordPressAdminApi();
        $menu = new AdminMenu($api);
        $dashboard = new DashboardPage($this->application);
        $pages = new AdminPageRegistry();
        $pages->addPage($dashboard);

        $this->manager = new AdminManager(
            $menu,
            $pages
        );

        $this->container->set(AdminApiInterface::class, $api);
        $this->container->set(WordPressAdminApi::class, $api);
        $this->container->set(AdminMenu::class, $menu);
        $this->container->set(AdminPageRegistry::class, $pages);
        $this->container->set(AdminPageInterface::class, $dashboard);
        $this->container->set(DashboardPage::class, $dashboard);
        $this->container->set(AdminManager::class, $this->manager);
    }

    public function boot(KernelInterface $kernel): void
    {
        if ($this->manager === null) {
            throw new \LogicException('AdminServiceProvider must be registered before it is booted.');
        }

        $this->hooks->action('admin_menu', $this->manager, 10, 0);
    }
}
