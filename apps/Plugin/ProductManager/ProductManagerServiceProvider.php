<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

use LogicException;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\WordPressEnvatoTransport;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
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
        $transport = new WordPressEnvatoTransport();
        $envatoClient = new EnvatoClient(
            $transport(...),
            $mapper
        );
        $tagRepository = new WordPressCatalogTagRepository();
        $tagSelector = new ExistingTagSelector($tagRepository);
        $functionCaller = new WordPressFunctionCaller();
        $draftGateway = new WordPressWooCommerceDraftGateway(
            $functionCaller(...)
        );
        $draftValidator = new ProductDraftValidator();
        $draftCreator = new ProductDraftCreator(
            $draftGateway,
            $draftValidator
        );
        $page = new ProductManagerPage();

        $registry->addSubmenu($page);

        $this->container->set(
            EnvatoItemMapper::class,
            $mapper
        );
        $this->container->set(
            WordPressEnvatoTransport::class,
            $transport
        );
        $this->container->set(
            EnvatoClientInterface::class,
            $envatoClient
        );
        $this->container->set(
            EnvatoClient::class,
            $envatoClient
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
            WordPressFunctionCaller::class,
            $functionCaller
        );
        $this->container->set(
            ProductDraftGatewayInterface::class,
            $draftGateway
        );
        $this->container->set(
            WordPressWooCommerceDraftGateway::class,
            $draftGateway
        );
        $this->container->set(
            ProductDraftValidator::class,
            $draftValidator
        );
        $this->container->set(
            ProductDraftCreator::class,
            $draftCreator
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
