<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;
use Throwable;

final class PreparedEnglishProductContent
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function filterShortDescription(mixed $content): mixed
    {
        return $this->preparedContent(
            $content,
            '_wp_shop_en_short_description'
        );
    }

    public function filterLongDescription(mixed $content): mixed
    {
        return $this->preparedContent(
            $content,
            '_wp_shop_en_long_description'
        );
    }

    private function preparedContent(
        mixed $content,
        string $metaKey
    ): mixed {
        if (! is_string($content) || ! $this->isEnglishRequest()) {
            return $content;
        }

        $productId = $this->productId();

        if ($productId <= 0) {
            return $content;
        }

        $prepared = ($this->call)(
            'get_post_meta',
            $productId,
            $metaKey,
            true
        );

        if (! is_string($prepared) || trim($prepared) === '') {
            return $content;
        }

        return $prepared;
    }

    private function isEnglishRequest(): bool
    {
        try {
            if ((bool) ($this->call)('is_admin')) {
                return false;
            }

            $locale = ($this->call)('get_locale');

            if ($this->isEnglishLocale($locale)) {
                return true;
            }

            if ((bool) ($this->call)('function_exists', 'determine_locale')) {
                $locale = ($this->call)('determine_locale');

                if ($this->isEnglishLocale($locale)) {
                    return true;
                }
            }
        } catch (Throwable) {
            // Fall back to the translated URL below.
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (! is_string($requestUri) || $requestUri === '') {
            return false;
        }

        $path = parse_url($requestUri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return preg_match(
            '#^/(?:en|en-us)(?:/|$)#i',
            $path
        ) === 1;
    }

    private function isEnglishLocale(mixed $locale): bool
    {
        if (! is_string($locale)) {
            return false;
        }

        $locale = strtolower(
            str_replace('-', '_', trim($locale))
        );

        return $locale === 'en'
            || str_starts_with($locale, 'en_');
    }

    private function productId(): int
    {
        try {
            $queriedId = (int) ($this->call)(
                'get_queried_object_id'
            );

            if (
                $queriedId > 0
                && ($this->call)('get_post_type', $queriedId) === 'product'
            ) {
                return $queriedId;
            }

            $currentId = (int) ($this->call)('get_the_ID');

            if (
                $currentId > 0
                && ($this->call)('get_post_type', $currentId) === 'product'
            ) {
                return $currentId;
            }
        } catch (Throwable) {
            return 0;
        }

        return 0;
    }
}
