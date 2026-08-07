<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class ThemeStructureValidationFailed extends RuntimeException
{
    public static function missingEntryPoint(
        string $sourceDirectory
    ): self {
        return new self(
            sprintf(
                'Theme structure "%s" must contain '
                    . 'a readable regular file "index.php" '
                    . 'or "templates/index.html".',
                $sourceDirectory
            )
        );
    }

    public static function entryIsSymbolicLink(
        string $relativePath
    ): self {
        return new self(
            sprintf(
                'Theme structure entry "%s" cannot be '
                    . 'a symbolic link.',
                $relativePath
            )
        );
    }

    public static function entryUnavailable(
        string $relativePath
    ): self {
        return new self(
            sprintf(
                'Theme structure entry "%s" must be '
                    . 'a readable regular file.',
                $relativePath
            )
        );
    }
}
