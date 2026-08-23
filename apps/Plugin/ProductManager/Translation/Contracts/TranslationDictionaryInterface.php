<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation\Contracts;

use WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus;

interface TranslationDictionaryInterface
{
    /**
     * @param array<string, string> $map
     */
    public function status(array $map): TranslationDictionaryStatus;

    public function backup(
        int $productId,
        string $slug,
        TranslationDictionaryStatus $status
    ): void;

    public function fill(
        TranslationDictionaryStatus $status
    ): int;
}
