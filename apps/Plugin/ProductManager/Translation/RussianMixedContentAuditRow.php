<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

final readonly class RussianMixedContentAuditRow
{
    /**
     * @param list<RussianMixedContentAuditFinding> $findings
     */
    public function __construct(
        public int $productId,
        public string $title,
        public string $status,
        public array $findings
    ) {
    }
}
