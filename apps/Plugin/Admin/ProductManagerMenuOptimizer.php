<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;

final class ProductManagerMenuOptimizer
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function optimize(): void
    {
        foreach ($this->hiddenSlugs() as $slug) {
            ($this->call)(
                'remove_submenu_page',
                'wp-shop-builder',
                $slug
            );
        }
    }

    /**
     * @return list<string>
     */
    public function hiddenSlugs(): array
    {
        return [
            // WordPress adds the parent page as the first submenu entry.
            'wp-shop-builder',

            // Advanced/manual update pages. The normal workflow uses
            // Import Queue + Update Queue + Full Update Scan.
            'wp-shop-builder-product-update',
            'wp-shop-builder-product-update-scanner',

            // Completed one-off naming/version migration and diagnostics.
            'wp-shop-builder-vendor-naming-audit',
            'wp-shop-builder-title-version-audit',
            'wp-shop-builder-vendor-naming-review-v4',
            'wp-shop-builder-vendor-naming-review-v5',
            'wp-shop-builder-vendor-canonical-naming-migration',
            'wp-shop-builder-vendor-canonical-naming-migration-v2',
            'wp-shop-builder-elementor-pro-trp-preflight',

            // Superseded cover experiments. New Vendor covers are generated
            // directly from Import Queue by Vendor AI Cover.
            'wp-shop-builder-vendor-cover-audit',
            'wp-shop-builder-vendor-cover-generator',
        ];
    }
}
