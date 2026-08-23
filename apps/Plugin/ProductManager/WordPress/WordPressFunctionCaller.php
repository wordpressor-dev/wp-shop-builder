<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\WordPress;

use Closure;
use RuntimeException;

final class WordPressFunctionCaller
{
    public function __invoke(
        string $name,
        mixed ...$arguments
    ): mixed {
        if (! is_callable($name)) {
            throw new RuntimeException(
                'WordPress/WooCommerce function is unavailable: ' . $name
            );
        }

        return Closure::fromCallable($name)(...$arguments);
    }
}
