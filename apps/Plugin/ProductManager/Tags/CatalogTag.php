<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

final readonly class CatalogTag
{
    public function __construct(
        public string $name,
        public string $slug
    ) {
    }

    public function line(): string
    {
        return $this->name . '|' . $this->slug;
    }
}
