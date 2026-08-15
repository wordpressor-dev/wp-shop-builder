<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

use DateTimeImmutable;
use InvalidArgumentException;

final class EnvatoItemMapper
{
    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $versionPayload
     */
    public function map(
        array $item,
        array $versionPayload,
        string $requestedUrl
    ): EnvatoItem {
        $itemId = $this->integer($item['id'] ?? null);
        $name = $this->string($item['name'] ?? null);

        if ($itemId <= 0) {
            throw new InvalidArgumentException(
                'Envato item ID is missing.'
            );
        }

        if ($name === '') {
            throw new InvalidArgumentException(
                'Envato item name is missing.'
            );
        }

        $salesPage = $this->firstString([
            $item['url'] ?? null,
            $requestedUrl,
        ]);

        $version = $this->firstString([
            $versionPayload['version'] ?? null,
            $versionPayload['wordpress_theme_metadata']['version'] ?? null,
            $versionPayload['wordpress_plugin_metadata']['version'] ?? null,
            $versionPayload['item']['wordpress_theme_metadata']['version'] ?? null,
            $item['wordpress_theme_metadata']['version'] ?? null,
            $item['wordpress_plugin_metadata']['version'] ?? null,
        ]);

        $developer = $this->firstString([
            $item['author_username'] ?? null,
            $item['wordpress_theme_metadata']['author_name'] ?? null,
        ]);

        $updatedDate = $this->date(
            $this->string($item['updated_at'] ?? null)
        );

        $baseTitle = preg_replace(
            '/\s+-\s+/u',
            ' – ',
            html_entity_decode(
                $name,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        if (! is_string($baseTitle)) {
            $baseTitle = $name;
        }

        $baseTitle = trim($baseTitle);
        $productSlug = $this->productSlug($baseTitle);
        $urlSlug = $this->itemUrlSlug($salesPage);
        $skuFilename = '';

        if ($urlSlug !== '' && $version !== '') {
            $skuFilename = sprintf(
                'themeforest-%d-%s-%s.zip',
                $itemId,
                $urlSlug,
                $version
            );
        }

        return new EnvatoItem(
            $itemId,
            $baseTitle,
            $productSlug,
            $version,
            $updatedDate,
            $developer,
            $salesPage,
            $this->integer($item['number_of_sales'] ?? 0),
            $this->nullableString($item['published_at'] ?? null),
            $this->tags($item['tags'] ?? []),
            $skuFilename,
            $item
        );
    }

    /**
     * @param list<mixed> $values
     */
    private function firstString(array $values): string
    {
        foreach ($values as $value) {
            $string = $this->string($value);

            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    private function string(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $string = $this->string($value);

        return $string === '' ? null : $string;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))
                ->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function productSlug(string $title): string
    {
        $parts = preg_split(
            '/[\s–—\-|:]+/u',
            $title
        );

        $first = is_array($parts)
            ? ($parts[0] ?? '')
            : '';

        return $this->slugify((string) $first);
    }

    private function itemUrlSlug(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return '';
        }

        if (
            preg_match(
                '~/(?:item)/([^/]+)/\d+/?$~',
                $path,
                $matches
            ) !== 1
        ) {
            return '';
        }

        return $this->slugify(
            (string) $matches[1]
        );
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        );

        if (! is_string($value)) {
            return '';
        }

        return trim($value, '-');
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];

        foreach ($value as $tag) {
            if (is_array($tag)) {
                $tag = $tag['name'] ?? $tag['value'] ?? null;
            }

            $string = $this->string($tag);

            if ($string !== '') {
                $tags[] = $string;
            }
        }

        return array_values(array_unique($tags));
    }
}
