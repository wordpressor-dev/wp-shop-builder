<?php

declare(strict_types=1);

namespace WPShop\Publisher\Validation;

use WPShop\Publisher\Contracts\ThemeCompatibilityValidatorInterface;
use WPShop\Publisher\Exception\ThemeCompatibilityValidationFailed;
use WPShop\Publisher\ThemeHeader;

final readonly class WordPressThemeCompatibilityValidator implements
    ThemeCompatibilityValidatorInterface
{
    private const string VERSION_PATTERN =
        '/^[0-9]+(?:\.[0-9]+){1,2}$/D';

    public function validate(ThemeHeader $header): void
    {
        $requiresAtLeast = $header->requiresAtLeast();
        $testedUpTo = $header->testedUpTo();
        $requiresPhp = $header->requiresPhp();

        if ($requiresAtLeast !== null) {
            $this->assertVersion(
                'Requires at least',
                $requiresAtLeast
            );
        }

        if ($testedUpTo !== null) {
            $this->assertVersion(
                'Tested up to',
                $testedUpTo
            );
        }

        if ($requiresPhp !== null) {
            $this->assertVersion(
                'Requires PHP',
                $requiresPhp
            );
        }

        if (
            $requiresAtLeast !== null
            && $testedUpTo !== null
            && version_compare(
                $testedUpTo,
                $requiresAtLeast,
                '<'
            )
        ) {
            throw ThemeCompatibilityValidationFailed
                ::testedVersionBelowMinimum(
                    $testedUpTo,
                    $requiresAtLeast
                );
        }
    }

    private function assertVersion(
        string $header,
        string $value
    ): void {
        if (
            preg_match(
                self::VERSION_PATTERN,
                $value
            ) !== 1
        ) {
            throw ThemeCompatibilityValidationFailed
                ::invalidVersion(
                    $header,
                    $value
                );
        }
    }
}
