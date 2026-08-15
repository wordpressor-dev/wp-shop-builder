<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\ProductManager\ProductManagerServiceProvider;
use WPShop\Core\Container\Container;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProviderTest extends TestCase
{
    public function testRegistersProductManagerSubmenuPage(): void
    {
        $container = new Container();
        $registry = new AdminPageRegistry();
        $container->set(AdminPageRegistry::class, $registry);

        $provider = new ProductManagerServiceProvider($container);
        $provider->register();

        self::assertSame(
            $container->get(ProductManagerPage::class),
            $registry->submenus()[0]
        );
        self::assertSame(
            'wp-shop-builder-product-manager',
            $registry->submenus()[0]->slug()
        );
    }

    public function testRequiresAdminPageRegistry(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'AdminPageRegistry must be registered before Product Manager.'
        );

        (new ProductManagerServiceProvider(new Container()))
            ->register();
    }
}
