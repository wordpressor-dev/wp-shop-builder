<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\Exception\ThemeCompatibilityValidationFailed;
use WPShop\Publisher\ThemeHeader;
use WPShop\Publisher\Validation\WordPressThemeCompatibilityValidator;

final class WordPressThemeCompatibilityValidatorTest extends TestCase
{
    #[DataProvider('validHeaders')]
    public function testAcceptsValidCompatibilityMetadata(
        ThemeHeader $header
    ): void {
        (new WordPressThemeCompatibilityValidator())
            ->validate($header);

        self::addToAssertionCount(1);
    }

    #[DataProvider('invalidVersions')]
    public function testRejectsMalformedCompatibilityVersion(
        string $field,
        string $value
    ): void {
        $header = match ($field) {
            'Requires at least' => new ThemeHeader(
                'Example Theme',
                '1.0.0',
                requiresAtLeast: $value
            ),
            'Tested up to' => new ThemeHeader(
                'Example Theme',
                '1.0.0',
                testedUpTo: $value
            ),
            'Requires PHP' => new ThemeHeader(
                'Example Theme',
                '1.0.0',
                requiresPhp: $value
            ),
        };

        $this->expectException(
            ThemeCompatibilityValidationFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Theme compatibility header "%s" '
                    . 'value "%s" is invalid.',
                $field,
                $value
            )
        );

        (new WordPressThemeCompatibilityValidator())
            ->validate($header);
    }

    public function testRejectsTestedVersionBelowMinimum(): void
    {
        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0',
            testedUpTo: '6.7.9',
            requiresAtLeast: '6.8'
        );

        $this->expectException(
            ThemeCompatibilityValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme "Tested up to" version "6.7.9" '
                . 'cannot be lower than '
                . '"Requires at least" version "6.8".'
        );

        (new WordPressThemeCompatibilityValidator())
            ->validate($header);
    }

    /**
     * @return iterable<string, array{ThemeHeader}>
     */
    public static function validHeaders(): iterable
    {
        yield 'all omitted' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0'
            ),
        ];

        yield 'requires at least' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0',
                requiresAtLeast: '6.8'
            ),
        ];

        yield 'tested up to' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0',
                testedUpTo: '6.9'
            ),
        ];

        yield 'requires php' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0',
                requiresPhp: '8.3'
            ),
        ];

        yield 'patch versions' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0',
                testedUpTo: '6.8.2',
                requiresAtLeast: '6.8.1',
                requiresPhp: '8.3.6'
            ),
        ];

        yield 'equal wordpress versions' => [
            new ThemeHeader(
                'Example Theme',
                '1.0.0',
                testedUpTo: '6.8',
                requiresAtLeast: '6.8'
            ),
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidVersions(): iterable
    {
        yield 'requires at least non-numeric' => [
            'Requires at least',
            'six.eight',
        ];

        yield 'requires at least path-like' => [
            'Requires at least',
            '../6.8',
        ];

        yield 'tested up to suffix' => [
            'Tested up to',
            '6.9-RC1',
        ];

        yield 'tested up to missing minor' => [
            'Tested up to',
            '6',
        ];

        yield 'requires php trailing dot' => [
            'Requires PHP',
            '8.3.',
        ];

        yield 'requires php repeated dot' => [
            'Requires PHP',
            '8..3',
        ];

        yield 'requires php too many components' => [
            'Requires PHP',
            '8.3.6.1',
        ];
    }
}
