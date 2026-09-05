<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

final readonly class EnvatoItemSearchResult
{
    public function __construct(
        public bool $success,
        public int $itemId,
        public string $title,
        public string $url,
        public int $score,
        public string $message
    ) {
    }
}
