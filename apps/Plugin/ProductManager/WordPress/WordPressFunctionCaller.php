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

        $title = '';
        try {
            $post = $this->__invoke('get_post', $productId);
            $title = is_object($post) && isset($post->post_title)
                ? trim((string) $post->post_title)
                : '';
        } catch (RuntimeException) {
        }

        if ($title !== '') {
            $explicitType = CatalogProductType::infer($title, '');
            if ($explicitType !== '') {
                return $explicitType;
            }
        }

        // Legacy products can carry a stale _wp_shop_product_type value from
        // earlier catalog tooling. The visible catalog category is stronger
        // evidence when the current title does not explicitly say theme/plugin.
        try {
            $category = $this->__invoke(
                'get_post_meta',
                $productId,
                'attr_category_value',
                true
            );
            $categoryType = $this->categoryType(
                is_scalar($category) ? (string) $category : ''
            );
            if ($categoryType !== '') {
                return $categoryType;
            }
        } catch (RuntimeException) {
        }

        try {
            $terms = $this->__invoke(
                'wp_get_post_terms',
                $productId,
                'product_cat',
                ['fields' => 'names']
            );
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $categoryType = $this->categoryType((string) $term);
                    if ($categoryType !== '') {
                        return $categoryType;
                    }
                }
            }
        } catch (RuntimeException) {
        }

        // Envato source host is also stronger evidence than stale legacy meta.
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

    private function categoryType(string $category): string
    {
        $category = mb_strtolower(trim($category), 'UTF-8');

        return match ($category) {
            'темы', 'themes' => CatalogProductType::THEME,
            'плагины', 'plugins' => CatalogProductType::PLUGIN,
            'шаблоны', 'templates' => CatalogProductType::TEMPLATE_KIT,
            default => '',
        };
    }
}
