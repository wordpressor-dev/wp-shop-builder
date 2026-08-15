<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation\Contracts;

use WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus;

interface TranslationRegistrarInterface
{
    public function registerPage(string $slug): string;

    /**
     * @return list<string>
     */
    public function registerMissing(
        TranslationDictionaryStatus $status
    ): array;

    /**
     * @return list<string>
     */
    public function missingDebugLines(
        TranslationDictionaryStatus $status
    ): array;
}
