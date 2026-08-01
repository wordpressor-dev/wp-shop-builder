<?php

declare(strict_types=1);

namespace WPShop\Release\Exception;

use RuntimeException;
use Throwable;

final class ReleasePersistenceFailed extends RuntimeException
{
    public static function creation(
        Throwable $previous
    ): self {
        return new self(
            'Release creation failed.',
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
                'Release %d update failed.',
                $id
            ),
            0,
            $previous
        );
    }

    public static function collection(
        Throwable $previous
    ): self {
        return new self(
            'Release collection lookup failed.',
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
                'Release lookup by %s "%s" failed.',
                $field,
                (string) $value
            ),
            0,
            $previous
        );
    }

    public static function blueprintVersionLookup(
        int $blueprintId,
        string $version,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Release lookup by blueprint %d and version "%s" failed.',
                $blueprintId,
                $version
            ),
            0,
            $previous
        );
    }
}
