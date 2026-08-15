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
            if (! $this->matches($blob, $rule['needles'])) {
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
                'needles' => ['software'],
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
