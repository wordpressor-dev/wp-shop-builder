<?php

declare(strict_types=1);

namespace WPShop\Core\Exception;

use LogicException;

final class ModuleAlreadyRegistered extends LogicException
{
    public static function forId(string $id): self
    {
        return new self(sprintf(
            'Module "%s" is already registered.',
            $id
        ));
    }
}