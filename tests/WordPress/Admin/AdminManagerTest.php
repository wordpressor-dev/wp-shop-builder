<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Admin\AdminManager;
use WPShop\WordPress\Admin\AdminMenu;
use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;

final class AdminManagerTest extends TestCase
{
    public function testRegistersDashboardPageWhenInvoked(): void
    {
        $api = $this->createMock(AdminApiInterface::class);
        $page = new class implements AdminPageInterface {
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
        $manager = new AdminManager(new AdminMenu($api), $page);

        $manager();
    }
}
