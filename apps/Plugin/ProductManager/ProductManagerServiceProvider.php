<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

use LogicException;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $registry = $this->container->get(
            AdminPageRegistry::class
        );

        if (! $registry instanceof AdminPageRegistry) {
            throw new LogicException(
                'AdminPageRegistry must be registered before Product Manager.'
            );
        }

        $page = new ProductManagerPage();
        $registry->addSubmenu($page);

        $this->container->set(
            ProductManagerPage::class,
            $page
        );
    }

    public function boot(KernelInterface $kernel): void
    {
    }
}
