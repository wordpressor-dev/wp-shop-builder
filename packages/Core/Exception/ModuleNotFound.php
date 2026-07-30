<?php

declare(strict_types=1);

namespace WPShop\Core\Exception;

use RuntimeException;

final class ModuleNotFound extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf(
            'Module "%s" was not found.',
            $id
        ));
    }
}
