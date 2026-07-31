<?php

declare(strict_types=1);

namespace WPShop\WordPress\Adapter;

use WPShop\WordPress\Contracts\HookAdapterInterface;
use WPShop\WordPress\Exception\WordPressFunctionUnavailable;

final class NativeHookAdapter implements HookAdapterInterface
{
    public function addAction(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        if (!function_exists('add_action')) {
            throw WordPressFunctionUnavailable::named('add_action');
        }

        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    public function addFilter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        if (!function_exists('add_filter')) {
            throw WordPressFunctionUnavailable::named('add_filter');
        }

        add_filter($hook, $callback, $priority, $acceptedArgs);
    }
}
