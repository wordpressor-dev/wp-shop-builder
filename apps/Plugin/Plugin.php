<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use WPShop\WordPress\Bootstrap\Bootstrap as WordPressBootstrap;

final class Plugin
{
    public const VERSION = '0.2.0';

    public function boot(): void
    {
        WordPressBootstrap::run();
    }
}
