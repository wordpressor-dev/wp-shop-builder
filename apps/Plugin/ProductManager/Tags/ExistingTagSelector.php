<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;

final class ExistingTagSelector
{
    public function __construct(
        private readonly CatalogTagRepositoryInterface $repository
    ) {
    }

    /**
     * @param array<string, mixed> $envatoItem
     * @return list<CatalogTag>
     */
    public function select(array $envatoItem): array
    {
        $blob = mb_strtolower(
            json_encode(
                $envatoItem,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '',
            'UTF-8'
        );

        $selected = [];

        foreach ($this->rules() as $rule) {
            $matched = $rule['slug'] === 'software'
                ? $this->matchesSoftwareNiche($envatoItem)
                : $this->matches($blob, $rule['needles']);

            if (! $matched) {
                continue;
            }

            if (
                ! $this->repository->existsInBoth(
                    $rule['name'],
                    $rule['slug']
                )
            ) {
                continue;
            }

            $selected[$rule['slug']] = new CatalogTag(
                $rule['name'],
                $rule['slug']
            );
        }

        return array_values($selected);
    }

    /**
     * @param array<string, mixed> $envatoItem
     */
    private function matchesSoftwareNiche(array $envatoItem): bool
    {
        if (! $this->isThemeItem($envatoItem)) {
            return false;
        }

        $name = $this->string($envatoItem['name'] ?? null);
        $classification = $this->string(
            $envatoItem['classification'] ?? null
        );
        $titleSignals = mb_strtolower(
            $name . ' ' . $classification,
            'UTF-8'
        );
        $phrases = [
            'software',
            'saas',
            'software company',
            'software startup',
            'software business',
            'software solution',
            'software product',
            'app landing',
            'technology company',
            'tech startup',
        ];

        if ($this->matches($titleSignals, $phrases)) {
            return true;
        }

        foreach ($this->tags($envatoItem['tags'] ?? []) as $tag) {
            $normalized = $this->normalizeTag($tag);

            if (
                in_array(
                    $normalized,
                    [
                        'software',
                        'saas',
                        'software company',
                        'software startup',
                        'software business',
                        'software solution',
                        'software product',
                        'app landing',
                        'app landing page',
                        'technology company',
                        'tech startup',
                        'saas startup',
                    ],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $envatoItem
     */
    private function isThemeItem(array $envatoItem): bool
    {
        if (is_array($envatoItem['wordpress_plugin_metadata'] ?? null)) {
            return false;
        }

        $url = $this->string($envatoItem['url'] ?? null);
        $host = strtolower(
            (string) parse_url(
                $url,
                PHP_URL_HOST
            )
        );
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === 'codecanyon.net') {
            return false;
        }

        return $host === 'themeforest.net'
            || is_array(
                $envatoItem['wordpress_theme_metadata'] ?? null
            );
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
                $tag = $tag['name']
                    ?? $tag['value']
                    ?? null;
            }

            $string = $this->string($tag);

            if ($string !== '') {
                $tags[] = $string;
            }
        }

        return array_values(array_unique($tags));
    }

    private function normalizeTag(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace(
            '/[^a-z0-9]+/i',
            ' ',
            $value
        );

        return is_string($value)
            ? trim(
                preg_replace('/\s+/', ' ', $value)
                ?? ''
            )
            : '';
    }

    private function string(mixed $value): string
    {
        return is_scalar($value)
            ? trim((string) $value)
            : '';
    }

    /**
     * @param list<string> $needles
     */
    private function matches(
        string $blob,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            if (
                mb_strpos(
                    $blob,
                    mb_strtolower($needle, 'UTF-8')
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name: string, slug: string, needles: list<string>}>
     */
    private function rules(): array
    {
        return [
            [
                'name' => 'elementor',
                'slug' => 'elementor',
                'needles' => ['elementor'],
            ],
            [
                'name' => 'woocommerce',
                'slug' => 'woocommerce',
                'needles' => ['woocommerce'],
            ],
            [
                'name' => 'wpml',
                'slug' => 'wpml',
                'needles' => ['wpml'],
            ],
            [
                'name' => 'rtl',
                'slug' => 'rtl',
                'needles' => ['"rtl"', ' rtl '],
            ],
            [
                'name' => 'bbpress',
                'slug' => 'bbpress',
                'needles' => ['bbpress'],
            ],
            [
                'name' => 'buddypress',
                'slug' => 'buddypress',
                'needles' => ['buddypress'],
            ],
            [
                'name' => 'dokan',
                'slug' => 'dokan',
                'needles' => ['dokan'],
            ],
            [
                'name' => 'lms',
                'slug' => 'lms',
                'needles' => [
                    '"lms"',
                    'learning management system',
                ],
            ],
            [
                'name' => 'multi-vendor',
                'slug' => 'multi-vendor',
                'needles' => [
                    'multi-vendor',
                    'multi vendor',
                    'multivendor',
                    'multi vendors',
                ],
            ],
            [
                'name' => 'торговая площадка',
                'slug' => 'marketplace',
                'needles' => [
                    'marketplace',
                    'digital marketplace',
                ],
            ],
            [
                'name' => 'цифровые товары',
                'slug' => 'digital-product',
                'needles' => [
                    'digital product',
                    'digital products',
                    'digital download',
                    'digital downloads',
                    'easy digital downloads',
                ],
            ],
            [
                'name' => 'интернет-магазин',
                'slug' => 'shop',
                'needles' => [
                    'online store',
                    'digital store',
                    'digital shop',
                ],
            ],
            [
                'name' => 'электронная коммерция',
                'slug' => 'ecommerce',
                'needles' => [
                    'ecommerce',
                    'e-commerce',
                ],
            ],
            [
                'name' => 'программное обеспечение',
                'slug' => 'software',
                'needles' => [],
            ],
            [
                'name' => 'музыка и группы',
                'slug' => 'music-bands',
                'needles' => [
                    'audio marketplace',
                    'music marketplace',
                ],
            ],
            [
                'name' => 'конструктор страниц',
                'slug' => 'page-builder',
                'needles' => [
                    'page builder',
                    'drag-and-drop builder',
                ],
            ],
            [
                'name' => 'gutenberg',
                'slug' => 'gutenberg',
                'needles' => [
                    '"gutenberg optimized":"yes"',
                    '"gutenberg_optimized":"yes"',
                    'gutenberg optimized: yes',
                ],
            ],
        ];
    }
}
