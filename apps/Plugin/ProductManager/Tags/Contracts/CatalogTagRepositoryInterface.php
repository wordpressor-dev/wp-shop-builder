<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags\Contracts;

interface CatalogTagRepositoryInterface
{
    public function existsInBoth(
        string $name,
        string $slug
    ): bool;
}
