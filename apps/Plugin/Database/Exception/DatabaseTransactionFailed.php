<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database\Exception;

use RuntimeException;
use Throwable;

final class DatabaseTransactionFailed extends RuntimeException
{
    private function __construct(
        string $message,
        Throwable $previous,
        private readonly ?Throwable $rollbackFailure = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }

    public static function operation(
        string $operation,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Database transaction "%s" failed: %s',
                $operation,
                $previous->getMessage()
            ),
            $previous
        );
    }

    public static function rollback(
        Throwable $failure,
        Throwable $rollbackFailure
    ): self {
        return new self(
            sprintf(
                'Database transaction rollback failed after "%s": %s',
                $failure->getMessage(),
                $rollbackFailure->getMessage()
            ),
            $failure,
            $rollbackFailure
        );
    }

    public function rollbackFailure(): ?Throwable
    {
        return $this->rollbackFailure;
    }
}
