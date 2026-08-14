<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

final class AdminManager
{
    public function __construct(
        private readonly AdminMenu $menu,
        private readonly AdminPageRegistry $pages
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->pages->pages() as $page) {
            $this->menu->register($page);
        }

        foreach ($this->pages->submenus() as $page) {
            $this->menu->registerSubmenu($page);
        }
    }
}
