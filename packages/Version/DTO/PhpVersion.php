<?php

declare(strict_types=1);

namespace WPShop\Version\DTO;

final readonly class PhpVersion
{
    public function __construct(
        public string $version
    ) {
    }
}
