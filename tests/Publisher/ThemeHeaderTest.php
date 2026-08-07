<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\ThemeHeader;

final class ThemeHeaderTest extends TestCase
{
    public function testNormalizesAndExposesMetadata(): void
    {
        $header = new ThemeHeader(
            ' Example Theme ',
            ' 1.2.3 ',
            ' 6.9 ',
            ' 6.8 ',
            ' 8.3 ',
            ' example-theme ',
            ' parent-theme '
        );

        self::assertSame(
            'Example Theme',
            $header->name()
        );

        self::assertSame(
            '1.2.3',
            $header->version()
        );

        self::assertSame(
            '6.9',
            $header->testedUpTo()
        );

        self::assertSame(
            '6.8',
            $header->requiresAtLeast()
        );

        self::assertSame(
            '8.3',
            $header->requiresPhp()
        );

        self::assertSame(
            'example-theme',
            $header->textDomain()
        );

        self::assertSame(
            'parent-theme',
            $header->template()
        );
    }

    public function testNormalizesEmptyOptionalTextToNull(): void
    {
        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0',
            ' ',
            '',
            '   ',
            ' ',
            ''
        );

        self::assertNull(
            $header->testedUpTo()
        );

        self::assertNull(
            $header->requiresAtLeast()
        );

        self::assertNull(
            $header->requiresPhp()
        );

        self::assertNull(
            $header->textDomain()
        );

        self::assertNull(
            $header->template()
        );
    }

    #[DataProvider('invalidRequiredHeaders')]
    public function testRejectsEmptyRequiredHeader(
        string $name,
        string $version,
        string $message
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage($message);

        new ThemeHeader(
            $name,
            $version
        );
    }

    public function testRejectsNullByteInHeaderValue(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Theme header template cannot contain null bytes.'
        );

        new ThemeHeader(
            'Example Theme',
            '1.0.0',
            template: "parent\0theme"
        );
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     string,
     *     string
     * }>
     */
    public static function invalidRequiredHeaders(): iterable
    {
        yield 'name' => [
            ' ',
            '1.0.0',
            'Theme header name cannot be empty.',
        ];

        yield 'version' => [
            'Example Theme',
            '',
            'Theme header version cannot be empty.',
        ];
    }
}
