<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

interface ModuleInterface extends BootableInterface
{
    public function id(): string;

    public function name(): string;
}