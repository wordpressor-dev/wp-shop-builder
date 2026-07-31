<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Exception;

use RuntimeException;

final class BlueprintNotFound extends RuntimeException
{
    public static function byId(int $id): self
    {
        return new self(
            sprintf(
                'Blueprint with identifier %d was not found.',
                $id
            )
        );
    }

    public static function byUuid(string $uuid): self
    {
        return new self(
            sprintf(
                'Blueprint with UUID "%s" was not found.',
                $uuid
            )
        );
    }
}
