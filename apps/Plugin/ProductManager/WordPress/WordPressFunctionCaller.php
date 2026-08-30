<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\WordPress;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

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

        $result = Closure::fromCallable($name)(...$arguments);

        if (
            $name !== 'get_post_meta'
            || ($arguments[1] ?? null) !== '_wp_shop_product_type'
            || ($arguments[2] ?? null) !== true
        ) {
            return $result;
        }

        $productId = (int) ($arguments[0] ?? 0);
        if ($productId <= 0 || ! is_callable('get_post')) {
            return $result;
        }

        $post = Closure::fromCallable('get_post')($productId);
        $title = is_object($post) && isset($post->post_title)
            ? trim((string) $post->post_title)
            : '';
        if ($title === '') {
            return $result;
        }

        $explicitType = CatalogProductType::infer($title, '');

        // Legacy products can carry a stale _wp_shop_product_type value from
        // earlier catalog tooling. An explicit type in the current product
        // title is safer evidence than that stale metadata.
        return $explicitType !== '' ? $explicitType : $result;
    }
}
