<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;
use Throwable;

final class PackageAssemblyFailed extends RuntimeException
{
    public static function sourceDirectoryUnavailable(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Package assembly source directory "%s" was not found or is not readable.',
                $directory
            )
        );
    }

    public static function sourceEntryUnreadable(
        string $path
    ): self {
        return new self(
            sprintf(
                'Package source entry "%s" is not readable.',
                $path
            )
        );
    }

    public static function symbolicLinkNotAllowed(
        string $path
    ): self {
        return new self(
            sprintf(
                'Package source entry "%s" cannot be a symbolic link.',
                $path
            )
        );
    }

    public static function unsupportedSourceEntry(
        string $path
    ): self {
        return new self(
            sprintf(
                'Package source entry "%s" is not a supported file or directory.',
                $path
            )
        );
    }

    public static function targetAlreadyExists(
        string $path
    ): self {
        return new self(
            sprintf(
                'Prepared package "%s" already exists.',
                $path
            )
        );
    }

    public static function directoryCreationFailed(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Package workspace directory "%s" could not be created.',
                $directory
            )
        );
    }

    public static function archiveCreationFailed(
        string $path,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Prepared package "%s" could not be created.',
                $path
            ),
            0,
            $previous
        );
    }

    public static function partialCleanupFailed(
        string $path,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Partial prepared package "%s" could not be removed.',
                $path
            ),
            0,
            $previous
        );
    }
}
