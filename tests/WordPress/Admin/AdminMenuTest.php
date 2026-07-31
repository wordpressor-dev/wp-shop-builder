<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Admin\AdminMenu;
use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;

final class AdminMenuTest extends TestCase
{
    public function testRegistersTopLevelWordPressMenu(): void
    {
        $api = $this->createMock(AdminApiInterface::class);
        $page = new class implements AdminPageInterface {
            public function slug(): string
            {
                return 'builder-dashboard';
            }

            public function title(): string
            {
                return 'Builder';
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
                'Builder',
                'Builder',
                'manage_options',
                'builder-dashboard',
                self::isCallable(),
                'dashicons-admin-tools',
                58
            );

        (new AdminMenu($api))->register($page);
    }
}
