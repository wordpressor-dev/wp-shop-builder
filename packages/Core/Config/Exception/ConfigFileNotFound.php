<?php

declare(strict_types=1);

namespace WPShop\Core\Config\Exception;

use RuntimeException;

final class ConfigFileNotFound extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(
            sprintf('Configuration file "%s" was not found.', $path)
        );
    }
}
