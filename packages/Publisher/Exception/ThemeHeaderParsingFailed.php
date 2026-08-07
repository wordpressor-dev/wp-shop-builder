<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class ThemeHeaderParsingFailed extends RuntimeException
{
    public static function entryFileUnavailable(
        string $path
    ): self {
        return new self(
            sprintf(
                'Theme entry file "%s" was not found or is not readable.',
                $path
            )
        );
    }

    public static function entryFileOpenFailed(
        string $path
    ): self {
        return new self(
            sprintf(
                'Theme entry file "%s" could not be opened.',
                $path
            )
        );
    }

    public static function entryFileReadFailed(
        string $path
    ): self {
        return new self(
            sprintf(
                'Theme entry file "%s" could not be read.',
                $path
            )
        );
    }

    public static function requiredHeaderMissing(
        string $header,
        string $path
    ): self {
        return new self(
            sprintf(
                'Required theme header "%s" was not found in "%s".',
                $header,
                $path
            )
        );
    }

    public static function requiredHeaderEmpty(
        string $header,
        string $path
    ): self {
        return new self(
            sprintf(
                'Required theme header "%s" is empty in "%s".',
                $header,
                $path
            )
        );
    }

    public static function invalidHeaderValue(
        string $header,
        string $path
    ): self {
        return new self(
            sprintf(
                'Theme header "%s" contains an invalid value in "%s".',
                $header,
                $path
            )
        );
    }
}
