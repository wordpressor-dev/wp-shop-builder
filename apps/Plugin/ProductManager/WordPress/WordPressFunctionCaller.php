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
        $productTypeRead = $name === 'get_post_meta'
            && ($arguments[1] ?? null) === '_wp_shop_product_type'
            && ($arguments[2] ?? null) === true;

        if (! is_callable($name)) {
            throw new RuntimeException(
                'WordPress/WooCommerce function is unavailable: ' . $name
            );
        }

        $result = Closure::fromCallable($name)(...$arguments);

        if (! $productTypeRead) {
            return $result;
        }

        $productId = (int) ($arguments[0] ?? 0);
        if ($productId <= 0) {
            return $result;
        }

        // Technical type is intentionally independent from the visible catalog
        // category. A theme add-on can be merchandised under Themes while its
        // installable archive is still a WordPress plugin.
        try {
            $post = $this->__invoke('get_post', $productId);
            $title = is_object($post) && isset($post->post_title)
                ? trim((string) $post->post_title)
                : '';
            if ($title !== '') {
                $explicitType = CatalogProductType::infer($title, '');
                if ($explicitType !== '') {
                    return $explicitType;
                }
            }
        } catch (RuntimeException) {
        }

        // Canonical Envato SKU/archive prefixes are strong technical evidence.
        // This repairs stale legacy meta without confusing catalog placement
        // with the actual installable package type.
        try {
            $sku = $this->__invoke(
                'get_post_meta',
                $productId,
                '_sku',
                true
            );
            $archiveType = CatalogProductType::inferArchiveName(
                is_scalar($sku) ? (string) $sku : ''
            );
            if ($archiveType !== '') {
                return $archiveType;
            }
        } catch (RuntimeException) {
        }

        try {
            $salesPage = $this->__invoke(
                'get_post_meta',
                $productId,
                'sales_page',
                true
            );
            $sourceType = CatalogProductType::infer(
                '',
                is_scalar($salesPage) ? (string) $salesPage : ''
            );
            if ($sourceType !== '') {
                return $sourceType;
            }
        } catch (RuntimeException) {
        }

        return $result;
    }
}
