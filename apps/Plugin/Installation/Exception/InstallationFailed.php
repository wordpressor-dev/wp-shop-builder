<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Installation\Exception;

use RuntimeException;
use Throwable;

final class InstallationFailed extends RuntimeException
{
    public static function migration(
        string $version,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Migration for version %s failed: %s',
                $version,
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }

    public static function stateWrite(string $version): self
    {
        return new self(
            sprintf(
                'Unable to save installed plugin version %s.',
                $version
            )
        );
    }
}
