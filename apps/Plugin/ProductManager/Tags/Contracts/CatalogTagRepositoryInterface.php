<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags\Contracts;

use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;

interface CatalogTagRepositoryInterface
{
    public function existsInBoth(
        string $name,
        string $slug
    ): bool;

    public function resolveInBoth(
        string $name,
        string $slug
    ): ?CatalogTag;
}
