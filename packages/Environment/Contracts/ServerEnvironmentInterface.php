<?php

declare(strict_types=1);

namespace WPShop\Environment\Contracts;

interface ServerEnvironmentInterface
{
    public function software(): string;

    public function phpSapi(): string;

    public function operatingSystem(): string;
}
