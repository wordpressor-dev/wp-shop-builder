<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class ThemeCompatibilityValidationFailed extends RuntimeException
{
    public static function invalidVersion(
        string $header,
        string $value
    ): self {
        return new self(
            sprintf(
                'Theme compatibility header "%s" value "%s" is invalid.',
                $header,
                $value
            )
        );
    }

    public static function testedVersionBelowMinimum(
        string $testedUpTo,
        string $requiresAtLeast
    ): self {
        return new self(
            sprintf(
                'Theme "Tested up to" version "%s" cannot be lower than '
                    . '"Requires at least" version "%s".',
                $testedUpTo,
                $requiresAtLeast
            )
        );
    }
}
