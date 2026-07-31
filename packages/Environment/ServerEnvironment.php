<?php

declare(strict_types=1);

namespace WPShop\Environment;

use WPShop\Environment\Contracts\ServerEnvironmentInterface;

final class ServerEnvironment implements ServerEnvironmentInterface
{
    public function software(): string
    {
        $software = $_SERVER['SERVER_SOFTWARE'] ?? null;

        return is_string($software) && $software !== ''
            ? $software
            : 'Unavailable';
    }

    public function phpSapi(): string
    {
        return PHP_SAPI;
    }

    public function operatingSystem(): string
    {
        return PHP_OS_FAMILY;
    }
}
