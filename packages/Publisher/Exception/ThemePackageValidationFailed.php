<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;
use Throwable;

final class ThemePackageValidationFailed extends RuntimeException
{
    public static function invalidHeader(
        string $entryPath,
        Throwable $previous
    ): self {
        return new self(
            sprintf(
                'Theme package header "%s" is invalid. %s',
                $entryPath,
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }

    public static function versionMismatch(
        string $releaseVersion,
        string $headerVersion
    ): self {
        return new self(
            sprintf(
                'Theme header version "%s" does not match '
                . 'Release version "%s".',
                $headerVersion,
                $releaseVersion
            )
        );
    }

    public static function invalidTemplateSlug(
        string $slug
    ): self {
        return new self(
            sprintf(
                'Theme Template slug "%s" is invalid.',
                $slug
            )
        );
    }
}
