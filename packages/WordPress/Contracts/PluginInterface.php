<?php

declare(strict_types=1);

namespace WPShop\WordPress\Contracts;

interface PluginInterface
{
    public function register(): void;

    public function boot(): void;
}
