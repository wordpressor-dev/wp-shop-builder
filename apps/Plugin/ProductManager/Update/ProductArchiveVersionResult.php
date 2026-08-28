<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductArchiveVersionResult
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public string $version,
        public array $logs
    ) {
    }
}
