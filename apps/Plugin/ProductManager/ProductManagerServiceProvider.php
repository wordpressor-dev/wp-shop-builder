<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

use LogicException;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
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

        $mapper = new EnvatoItemMapper();
        $tagRepository = new WordPressCatalogTagRepository();
        $tagSelector = new ExistingTagSelector($tagRepository);
        $page = new ProductManagerPage();

        $registry->addSubmenu($page);

        $this->container->set(
            EnvatoItemMapper::class,
            $mapper
        );
        $this->container->set(
            CatalogTagRepositoryInterface::class,
            $tagRepository
        );
        $this->container->set(
            WordPressCatalogTagRepository::class,
            $tagRepository
        );
        $this->container->set(
            ExistingTagSelector::class,
            $tagSelector
        );
        $this->container->set(
            ProductManagerPage::class,
            $page
        );
    }

    public function boot(KernelInterface $kernel): void
    {
    }
}
