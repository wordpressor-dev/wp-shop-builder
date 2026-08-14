<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin;

use WPShop\WordPress\Admin\Contracts\AdminApiInterface;
use WPShop\WordPress\Exception\WordPressFunctionUnavailable;

final class WordPressAdminApi implements AdminApiInterface
{
    public function addMenuPage(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback,
        string $iconUrl = '',
        ?int $position = null
    ): void {
        if (!function_exists('add_menu_page')) {
            throw WordPressFunctionUnavailable::named('add_menu_page');
        }

        add_menu_page(
            $pageTitle,
            $menuTitle,
            $capability,
            $menuSlug,
            $callback,
            $iconUrl,
            $position
        );
    }

    public function addSubmenuPage(
        string $parentSlug,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback
    ): void {
        if (!function_exists('add_submenu_page')) {
            throw WordPressFunctionUnavailable::named('add_submenu_page');
        }

        add_submenu_page(
            $parentSlug,
            $pageTitle,
            $menuTitle,
            $capability,
            $menuSlug,
            $callback
        );
    }
}
