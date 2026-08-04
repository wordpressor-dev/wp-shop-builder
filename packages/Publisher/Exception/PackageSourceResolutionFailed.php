<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class PackageSourceResolutionFailed extends RuntimeException
{
    public static function sourceDirectoryUnavailable(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Package source directory "%s" was not found or is not readable.',
                $directory
            )
        );
    }

    public static function sourceDirectoryIsSymbolicLink(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Package source directory "%s" cannot be a symbolic link.',
                $directory
            )
        );
    }

    public static function entryFileUnavailable(
        string $path
    ): self {
        return new self(
            sprintf(
                'Package entry file "%s" was not found or is not readable.',
                $path
            )
        );
    }

    public static function entryFileIsSymbolicLink(
        string $path
    ): self {
        return new self(
            sprintf(
                'Package entry file "%s" cannot be a symbolic link.',
                $path
            )
        );
    }
}
