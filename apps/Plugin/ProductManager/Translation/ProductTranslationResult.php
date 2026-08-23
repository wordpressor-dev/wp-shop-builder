<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

final readonly class ProductTranslationResult
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public array $logs,
        public ?TranslationDictionaryStatus $status = null
    ) {
    }
}
