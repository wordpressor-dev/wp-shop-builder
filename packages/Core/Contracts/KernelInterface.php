<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

use WPShop\Core\Module\ModuleRegistry;

interface KernelInterface extends BootableInterface
{
    public function register(ModuleInterface $module): void;

    public function isBooted(): bool;

    public function modules(): ModuleRegistry;
}