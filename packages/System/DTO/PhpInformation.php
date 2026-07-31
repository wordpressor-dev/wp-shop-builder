<?php

declare(strict_types=1);

namespace WPShop\System\DTO;

final readonly class PhpInformation
{
    /**
     * @param list<string> $extensions
     */
    public function __construct(
        public string $version,
        public string $memoryLimit,
        public string $uploadMaxFilesize,
        public string $postMaxSize,
        public int $maxExecutionTime,
        public array $extensions
    ) {
    }
}
