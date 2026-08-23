<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

final readonly class TranslationDictionaryStatus
{
    /**
     * @param list<array{
     *     source: string,
     *     target: string,
     *     row: array<string, mixed>|null,
     *     action: string
     * }> $items
     */
    public function __construct(
        public bool $tableOk,
        public int $total,
        public int $exact,
        public int $keep,
        public int $fill,
        public int $missing,
        public array $items
    ) {
    }

    public function ready(): bool
    {
        return $this->tableOk
            && $this->fill === 0
            && $this->missing === 0;
    }
}
