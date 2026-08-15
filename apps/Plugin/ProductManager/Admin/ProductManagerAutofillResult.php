<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Admin;

final readonly class ProductManagerAutofillResult
{
    /**
     * @param array<string, string> $fields
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public array $fields,
        public array $logs
    ) {
    }
}
