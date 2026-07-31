<?php

declare(strict_types=1);

namespace WPShop\System\DTO;

final readonly class ServerInformation
{
    public function __construct(
        public string $software,
        public string $phpSapi,
        public string $operatingSystem
    ) {
    }
}
