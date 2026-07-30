<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

interface BootableInterface
{
    public function boot(): void;
}
