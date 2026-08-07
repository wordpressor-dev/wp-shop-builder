<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Exception\ThemeHeaderParsingFailed;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;

final class WordPressThemeHeaderParserTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-theme-header-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[DataProvider('lineEndings')]
    public function testParsesSupportedLineEndings(
        string $lineEnding
    ): void {
        $path = $this->writeEntry(
            implode(
                $lineEnding,
                [
                    '/*',
                    ' * Theme Name: Example Theme',
                    ' * Version: 1.2.3',
                    ' */',
                    '',
                ]
            )
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'Example Theme',
            $header->name()
        );

        self::assertSame(
            '1.2.3',
            $header->version()
        );

        self::assertNull(
            $header->testedUpTo()
        );

        self::assertNull(
            $header->template()
        );
    }

    public function testParsesCaseInsensitiveTrimmedHeaders(): void
    {
        $path = $this->writeEntry(
            <<<'CSS'
/*
 * theme name:   Example Theme
 * VERSION:  2.4.0
 * tested UP to:  6.9
 * requires AT least:  6.8
 * Requires php: 8.3
 * TEXT DOMAIN: example-theme
 * TEMPLATE: parent-theme
 */
CSS
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'Example Theme',
            $header->name()
        );

        self::assertSame(
            '2.4.0',
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

    public function testParsesUtf8BomAndCssCommentPrefixes(): void
    {
        $path = $this->writeEntry(
            "\xEF\xBB\xBF"
                . "/* Theme Name: Example Theme */\n"
                . "/** Version: 1.0.0 */\n"
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'Example Theme',
            $header->name()
        );

        self::assertSame(
            '1.0.0',
            $header->version()
        );
    }

    public function testUsesFirstValueForDuplicateHeader(): void
    {
        $path = $this->writeEntry(
            <<<'CSS'
/*
 * Theme Name: First Theme
 * Theme Name: Second Theme
 * Version: 1.0.0
 * Version: 2.0.0
 */
CSS
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'First Theme',
            $header->name()
        );

        self::assertSame(
            '1.0.0',
            $header->version()
        );
    }

    public function testReadsOnlyFirst8192Bytes(): void
    {
        $path = $this->writeEntry(
            str_repeat('x', 8192)
                . "\n/*\n"
                . " * Theme Name: Late Theme\n"
                . " * Version: 1.0.0\n"
                . " */\n"
        );

        $this->expectException(
            ThemeHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required theme header "Theme Name" '
                    . 'was not found in "%s".',
                $path
            )
        );

        $this->parser()->parse($path);
    }

    #[DataProvider('missingRequiredHeaders')]
    public function testRejectsMissingRequiredHeader(
        string $contents,
        string $header
    ): void {
        $path = $this->writeEntry($contents);

        $this->expectException(
            ThemeHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required theme header "%s" '
                    . 'was not found in "%s".',
                $header,
                $path
            )
        );

        $this->parser()->parse($path);
    }

    #[DataProvider('emptyRequiredHeaders')]
    public function testRejectsEmptyRequiredHeader(
        string $contents,
        string $header
    ): void {
        $path = $this->writeEntry($contents);

        $this->expectException(
            ThemeHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required theme header "%s" '
                    . 'is empty in "%s".',
                $header,
                $path
            )
        );

        $this->parser()->parse($path);
    }

    public function testRejectsUnavailableEntryFile(): void
    {
        $path = $this->directory
            . DIRECTORY_SEPARATOR
            . 'missing-style.css';

        $this->expectException(
            ThemeHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Theme entry file "%s" '
                    . 'was not found or is not readable.',
                $path
            )
        );

        $this->parser()->parse($path);
    }

    public function testRejectsNullByteInHeaderValue(): void
    {
        $path = $this->writeEntry(
            "/*\n"
                . " * Theme Name: Example Theme\n"
                . " * Version: 1.0.0\n"
                . " * Template: parent\0theme\n"
                . " */\n"
        );

        $this->expectException(
            ThemeHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Theme header "Template" contains '
                    . 'an invalid value in "%s".',
                $path
            )
        );

        $this->parser()->parse($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lineEndings(): iterable
    {
        yield 'LF' => ["\n"];
        yield 'CRLF' => ["\r\n"];
        yield 'CR' => ["\r"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingRequiredHeaders(): iterable
    {
        yield 'name' => [
            "/*\n * Version: 1.0.0\n */\n",
            'Theme Name',
        ];

        yield 'version' => [
            "/*\n * Theme Name: Example Theme\n */\n",
            'Version',
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function emptyRequiredHeaders(): iterable
    {
        yield 'name' => [
            "/*\n * Theme Name:   \n * Version: 1.0.0\n */\n",
            'Theme Name',
        ];

        yield 'version' => [
            "/*\n * Theme Name: Example Theme\n * Version:   \n */\n",
            'Version',
        ];
    }

    private function parser(): WordPressThemeHeaderParser
    {
        return new WordPressThemeHeaderParser();
    }

    private function writeEntry(string $contents): string
    {
        $path = $this->directory
            . DIRECTORY_SEPARATOR
            . 'style.css';

        self::assertIsInt(
            file_put_contents(
                $path,
                $contents
            )
        );

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (! file_exists($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();

            if ($entry->isFile() || $entry->isLink()) {
                @unlink($path);

                continue;
            }

            @rmdir($path);
        }

        @rmdir($directory);
    }
}
