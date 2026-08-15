<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato\Contracts;

use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;

interface EnvatoClientInterface
{
    public function fetch(
        string $itemUrl,
        string $token
    ): EnvatoItem;
}
