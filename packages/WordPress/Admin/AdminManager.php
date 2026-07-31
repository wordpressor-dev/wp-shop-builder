<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

use WPShop\WordPress\Admin\Contracts\AdminPageInterface;

final class AdminManager
{
    public function __construct(
        private readonly AdminMenu $menu,
        private readonly AdminPageInterface $dashboard
    ) {
    }

    public function __invoke(): void
    {
        $this->menu->register($this->dashboard);
    }
}
