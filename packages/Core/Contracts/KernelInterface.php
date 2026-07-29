<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

interface KernelInterface
{
    public function boot(): void;
}