<?php

declare(strict_types=1);

namespace WPShop\Manifest\Exception;

use RuntimeException;
use Throwable;

final class ManifestPersistenceFailed extends RuntimeException
{
    public static function creation(
        Throwable $previous
    ): self {
        return new self(
            'Manifest creation failed.',
            0,
            $previous
        );
    }

    public static function lookup(
        string $field,
        int $value,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Manifest lookup by %s "%d" failed.',
                $field,
                $value
            ),
            0,
            $previous
        );
    }
}
