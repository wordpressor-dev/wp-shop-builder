<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Admin\AdminManager;
use WPShop\WordPress\Admin\AdminMenu;
use WPShop\WordPress\Admin\AdminPageRegistry;
use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class AdminManagerTest extends TestCase
{
    public function testRegistersPagesAndSubmenusWhenInvoked(): void
    {
        $api = $this->createMock(AdminApiInterface::class);
        $dashboard = new class implements AdminPageInterface {
            public function slug(): string
            {
                return 'dashboard';
            }

            public function title(): string
            {
                return 'Dashboard';
            }

            public function capability(): string
            {
                return 'manage_options';
            }

            public function render(): void
            {
            }
        };
        $submenu = new class implements SubmenuPageInterface {
            public function parentSlug(): string
            {
                return 'dashboard';
            }

            public function slug(): string
            {
                return 'product-manager';
            }

            public function title(): string
            {
                return 'Product Manager';
            }

            public function capability(): string
            {
                return 'manage_woocommerce';
            }

            public function render(): void
            {
            }
        };

        $api->expects(self::once())
            ->method('addMenuPage')
            ->with(
                'Dashboard',
                'Dashboard',
                'manage_options',
                'dashboard',
                self::isCallable(),
                'dashicons-admin-tools',
                58
            );
        $api->expects(self::once())
            ->method('addSubmenuPage')
            ->with(
                'dashboard',
                'Product Manager',
                'Product Manager',
                'manage_woocommerce',
                'product-manager',
                self::isCallable()
            );

        $pages = new AdminPageRegistry();
        $pages->addPage($dashboard);
        $pages->addSubmenu($submenu);

        $manager = new AdminManager(
            new AdminMenu($api),
            $pages
        );

        $manager();
    }
}
