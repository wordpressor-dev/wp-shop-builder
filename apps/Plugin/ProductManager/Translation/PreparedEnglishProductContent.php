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
        if (! is_string($content) || ! $this->isEnglishProductRequest()) {
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

    private function isEnglishProductRequest(): bool
    {
        try {
            if ((bool) ($this->call)('is_admin')) {
                return false;
            }

            if (! (bool) ($this->call)('is_singular', 'product')) {
                return false;
            }

            if (! (bool) ($this->call)(
                'function_exists',
                'trp_get_current_language'
            )) {
                return false;
            }

            $language = ($this->call)('trp_get_current_language');

            return is_string($language)
                && strtolower(trim($language)) === 'en_us';
        } catch (Throwable) {
            return false;
        }
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
