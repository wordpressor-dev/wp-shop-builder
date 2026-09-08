<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

final readonly class RussianMixedContentAuditFinding
{
    public function __construct(
        public string $field,
        public string $classification,
        public string $fragment,
        public string $context
    ) {
    }
}
