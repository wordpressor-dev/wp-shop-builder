<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

final readonly class EnglishContentAuditRow
{
    /**
     * @param list<string> $locations
     * @param list<string> $issues
     */
    public function __construct(
        public int $productId,
        public string $title,
        public string $status,
        public array $locations,
        public array $issues,
        public bool $trpChecked
    ) {
    }
}
