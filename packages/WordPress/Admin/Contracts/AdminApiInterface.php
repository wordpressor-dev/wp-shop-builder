<?php

declare(strict_types=1);

namespace WPShop\WordPress\Admin\Contracts;

interface AdminApiInterface
{
    public function addMenuPage(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback,
        string $iconUrl = '',
        ?int $position = null
    ): void;
}
