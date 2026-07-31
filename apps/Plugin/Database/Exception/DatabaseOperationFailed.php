<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database\Exception;

use RuntimeException;
use Throwable;

final class DatabaseOperationFailed extends RuntimeException
{
    public static function operation(
        string $operation,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Database operation "%s" failed: %s',
                $operation,
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }
}
