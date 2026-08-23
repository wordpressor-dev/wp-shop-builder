<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

use WPShop\WordPress\Admin\Contracts\AdminPageInterface;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class AdminPageRegistry
{
    /** @var list<AdminPageInterface> */
    private array $pages = [];

    /** @var list<SubmenuPageInterface> */
    private array $submenus = [];

    public function addPage(AdminPageInterface $page): void
    {
        $this->pages[] = $page;
    }

    public function addSubmenu(SubmenuPageInterface $page): void
    {
        $this->submenus[] = $page;
    }

    /** @return list<AdminPageInterface> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return list<SubmenuPageInterface> */
    public function submenus(): array
    {
        return $this->submenus;
    }
}
