<?php

declare(strict_types=1);

namespace WPShop\System\DTO;

final readonly class WordPressInformation
{
    public function __construct(
        public string $version,
        public string $locale,
        public string $timezone,
        public bool $multisite,
        public bool $debug
    ) {
    }
}
