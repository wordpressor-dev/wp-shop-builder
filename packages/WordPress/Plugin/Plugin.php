<?php

declare(strict_types=1);

namespace WPShop\WordPress\Plugin;

use WPShop\WordPress\Contracts\PluginInterface;

abstract class Plugin implements PluginInterface
{
    abstract public function register(): void;

    abstract public function boot(): void;
}
