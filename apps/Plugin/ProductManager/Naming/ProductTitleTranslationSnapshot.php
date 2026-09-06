<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

final readonly class ProductTitleTranslationSnapshot
{
    /**
     * @param list<string> $translations
     */
    public function __construct(
        public string $state,
        public array $translations
    ) {
    }

    public function display(): string
    {
        return $this->translations === []
            ? ''
            : implode(' | ', $this->translations);
    }
}
