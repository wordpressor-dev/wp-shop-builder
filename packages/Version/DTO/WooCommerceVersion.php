<?php

declare(strict_types=1);

namespace WPShop\Version\DTO;

final readonly class WooCommerceVersion
{
    public function __construct(
        public string $version
    ) {
    }
}
