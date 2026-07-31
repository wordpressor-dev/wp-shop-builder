<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

final class Bootstrap
{
    public function boot(): void
    {
        (new Plugin())->boot();
    }
}