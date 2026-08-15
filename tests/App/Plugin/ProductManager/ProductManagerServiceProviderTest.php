<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\ProductManagerServiceProvider;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;
use WPShop\Core\Container\Container;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProviderTest extends TestCase
{
    public function testRegistersProductManagerServicesAndSubmenuPage(): void
    {
        $container = new Container();
        $registry = new AdminPageRegistry();
        $container->set(AdminPageRegistry::class, $registry);

        $provider = new ProductManagerServiceProvider($container);
        $provider->register();

        self::assertInstanceOf(
            EnvatoItemMapper::class,
            $container->get(EnvatoItemMapper::class)
        );
        self::assertInstanceOf(
            WordPressCatalogTagRepository::class,
            $container->get(CatalogTagRepositoryInterface::class)
        );
        self::assertInstanceOf(
            ExistingTagSelector::class,
            $container->get(ExistingTagSelector::class)
        );
        self::assertInstanceOf(
            WordPressFunctionCaller::class,
            $container->get(WordPressFunctionCaller::class)
        );
        self::assertInstanceOf(
            WordPressWooCommerceDraftGateway::class,
            $container->get(ProductDraftGatewayInterface::class)
        );
        self::assertInstanceOf(
            ProductDraftValidator::class,
            $container->get(ProductDraftValidator::class)
        );
        self::assertInstanceOf(
            ProductTaxonomyWriter::class,
            $container->get(ProductTaxonomyWriter::class)
        );
        self::assertInstanceOf(
            ProductMetadataWriter::class,
            $container->get(ProductMetadataWriter::class)
        );
        self::assertInstanceOf(
            SureRankWriter::class,
            $container->get(SureRankWriter::class)
        );
        self::assertInstanceOf(
            AdvancedLabelWriter::class,
            $container->get(AdvancedLabelWriter::class)
        );
        self::assertInstanceOf(
            ProductDraftCreator::class,
            $container->get(ProductDraftCreator::class)
        );
        self::assertSame(
            $container->get(ProductManagerPage::class),
            $registry->submenus()[0]
        );
        self::assertSame(
            'wp-shop-builder-product-manager',
            $registry->submenus()[0]->slug()
        );
    }
}
