<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PluginHeader;

final class PluginHeaderTest extends TestCase
{
    public function testNormalizesAndExposesMetadata(): void
    {
        $header = new PluginHeader(
            ' Example Plugin ',
            ' 1.2.3 ',
            ' 6.8 ',
            ' 8.3 ',
            [
                ' dependency-one ',
                'dependency-two',
            ],
            ' example-plugin '
        );

        self::assertSame(
            'Example Plugin',
            $header->name()
        );

        self::assertSame(
            '1.2.3',
            $header->version()
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
            [
                'dependency-one',
                'dependency-two',
            ],
            $header->requiredPlugins()
        );

        self::assertSame(
            'example-plugin',
            $header->textDomain()
        );
    }

    public function testNormalizesEmptyOptionalTextToNull(): void
    {
        $header = new PluginHeader(
            'Example Plugin',
            '1.0.0',
            ' ',
            '',
            [],
            '   '
        );

        self::assertNull(
            $header->requiresAtLeast()
        );

        self::assertNull(
            $header->requiresPhp()
        );

        self::assertSame(
            [],
            $header->requiredPlugins()
        );

        self::assertNull(
            $header->textDomain()
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

        new PluginHeader(
            $name,
            $version
        );
    }

    public function testRejectsEmptyRequiredPlugin(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Plugin header requiredPlugins '
                . 'cannot contain an empty value.'
        );

        new PluginHeader(
            'Example Plugin',
            '1.0.0',
            requiredPlugins: [' ']
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
            'Plugin header name cannot be empty.',
        ];

        yield 'version' => [
            'Example Plugin',
            '',
            'Plugin header version cannot be empty.',
        ];
    }
}
