<?php

declare(strict_types=1);

namespace WPShop\Core\Config\Exception;

use RuntimeException;

final class InvalidConfigFile extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(
            sprintf(
                'Configuration file "%s" must return an array.',
                $path
            )
        );
    }
}
