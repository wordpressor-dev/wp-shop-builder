<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Admin\Contracts\AdminPageInterface;

final class AdminMenu
{
    public function __construct(
        private readonly AdminApiInterface $api
    ) {
    }

    public function register(AdminPageInterface $page): void
    {
        $this->api->addMenuPage(
            $page->title(),
            $page->title(),
            $page->capability(),
            $page->slug(),
            $page->render(...),
            'dashicons-admin-tools',
            58
        );
    }
}
