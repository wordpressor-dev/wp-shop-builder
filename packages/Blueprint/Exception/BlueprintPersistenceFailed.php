<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Exception;

use RuntimeException;
use Throwable;

final class BlueprintPersistenceFailed extends RuntimeException
{
    public static function creation(
        Throwable $previous
    ): self {
        return new self(
            'Blueprint creation failed.',
            0,
            $previous
        );
    }

    public static function update(
        int $id,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Blueprint %d update failed.',
                $id
            ),
            0,
            $previous
        );
    }

    public static function deletion(
        int $id,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Blueprint %d deletion failed.',
                $id
            ),
            0,
            $previous
        );
    }

    public static function restoration(
        int $id,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Blueprint %d restoration failed.',
                $id
            ),
            0,
            $previous
        );
    }

    public static function lookup(
        string $field,
        int|string $value,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Blueprint lookup by %s "%s" failed.',
                $field,
                (string) $value
            ),
            0,
            $previous
        );
    }
}
