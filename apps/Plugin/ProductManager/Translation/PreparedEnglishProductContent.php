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
        try {
            ($this->call)(
                'add_filter',
                'the_content',
                [$this, 'filterPostContent'],
                PHP_INT_MAX,
                1
            );
        } catch (Throwable) {
            // WordPress hooks are not available in isolated tests.
        }
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

    public function filterPostContent(mixed $content): mixed
    {
        if (! is_string($content) || ! $this->isEnglishRequest()) {
            return $content;
        }

        $productId = $this->productId();

        if ($productId <= 0) {
            return $content;
        }

        try {
            $sourceLong = ($this->call)(
                'get_post_field',
                'post_content',
                $productId
            );
            $sourceShort = ($this->call)(
                'get_post_field',
                'post_excerpt',
                $productId
            );
        } catch (Throwable) {
            return $content;
        }

        $preparedLong = $this->preparedValue(
            $productId,
            '_wp_shop_en_long_description'
        );
        $preparedShort = $this->preparedValue(
            $productId,
            '_wp_shop_en_short_description'
        );

        if (
            is_string($sourceLong)
            && trim($sourceLong) !== ''
            && $preparedLong !== ''
            && (
                $this->normalizedText($content) === $this->normalizedText($sourceLong)
                || $this->sameHtmlStructure($content, $sourceLong)
            )
        ) {
            return $preparedLong;
        }

        $patched = $content;

        if (is_string($sourceLong) && $preparedLong !== '') {
            $patched = $this->replaceSourceFragment(
                $patched,
                $sourceLong,
                $preparedLong
            );
        }

        if (is_string($sourceShort) && $preparedShort !== '') {
            $patched = $this->replaceSourceFragment(
                $patched,
                $sourceShort,
                $preparedShort
            );
        }

        return $patched;
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

        $prepared = $this->preparedValue($productId, $metaKey);

        return $prepared !== '' ? $prepared : $content;
    }

    private function preparedValue(int $productId, string $metaKey): string
    {
        try {
            $prepared = ($this->call)(
                'get_post_meta',
                $productId,
                $metaKey,
                true
            );
        } catch (Throwable) {
            return '';
        }

        return is_string($prepared) ? trim($prepared) : '';
    }

    private function replaceSourceFragment(
        string $content,
        string $source,
        string $prepared
    ): string {
        $source = trim($source);
        $prepared = trim($prepared);

        if ($source === '' || $prepared === '') {
            return $content;
        }

        $exact = str_replace($source, $prepared, $content, $count);

        if ($count > 0) {
            return $exact;
        }

        $sourceText = $this->plainText($source);
        $preparedText = $this->plainText($prepared);

        if ($sourceText === '' || $preparedText === '') {
            return $content;
        }

        return str_replace($sourceText, $preparedText, $content);
    }

    private function normalizedText(string $content): string
    {
        return mb_strtolower($this->plainText($content), 'UTF-8');
    }

    private function sameHtmlStructure(string $left, string $right): bool
    {
        $leftStructure = $this->htmlStructure($left);
        $rightStructure = $this->htmlStructure($right);

        return $leftStructure !== []
            && $leftStructure === $rightStructure;
    }

    /**
     * @return list<string>
     */
    private function htmlStructure(string $content): array
    {
        $matches = [];

        if (
            preg_match_all(
                '/<\/?([a-z][a-z0-9]*)\b[^>]*>/i',
                $content,
                $matches,
                PREG_SET_ORDER
            ) !== false
        ) {
            $structure = [];

            foreach ($matches as $match) {
                $tag = strtolower((string) $match[1]);

                $isClosing = str_starts_with(
                    ltrim((string) $match[0]),
                    '</'
                );
                $structure[] = ($isClosing ? '/' : '') . $tag;
            }

            return $structure;
        }

        return [];
    }

    private function plainText(string $content): string
    {
        $content = html_entity_decode(
            strip_tags($content),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim((string) preg_replace('/\s+/u', ' ', $content));
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
