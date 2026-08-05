<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Exception\PluginHeaderParsingFailed;
use WPShop\Publisher\Parser\WordPressPluginHeaderParser;

final class WordPressPluginHeaderParserTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-plugin-header-'
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
                    '<?php',
                    '/*',
                    ' * Plugin Name: Example Plugin',
                    ' * Version: 1.2.3',
                    ' */',
                    '',
                ]
            )
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'Example Plugin',
            $header->name()
        );

        self::assertSame(
            '1.2.3',
            $header->version()
        );

        self::assertNull(
            $header->requiresAtLeast()
        );

        self::assertSame(
            [],
            $header->requiredPlugins()
        );
    }

    public function testParsesCaseInsensitiveTrimmedHeaders(): void
    {
        $path = $this->writeEntry(
            <<<'PHP'
<?php
/*
 * plugin name:   Example Plugin
 * VERSION:  2.4.0
 * requires AT least:  6.8
 * Requires php: 8.3
 * requires plugins: dependency-one,  dependency-two
 * TEXT DOMAIN: example-plugin
 */

PHP
        );

        $header = $this->parser()->parse($path);

        self::assertSame(
            'Example Plugin',
            $header->name()
        );

        self::assertSame(
            '2.4.0',
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

    public function testReadsOnlyFirst8192Bytes(): void
    {
        $path = $this->writeEntry(
            str_repeat('x', 8192)
                . "\n/*\n"
                . " * Plugin Name: Late Plugin\n"
                . " * Version: 1.0.0\n"
                . " */\n"
        );

        $this->expectException(
            PluginHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required plugin header "Plugin Name" '
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
            PluginHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required plugin header "%s" '
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
            PluginHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required plugin header "%s" '
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
            . 'missing.php';

        $this->expectException(
            PluginHeaderParsingFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Plugin entry file "%s" '
                    . 'was not found or is not readable.',
                $path
            )
        );

        $this->parser()->parse($path);
    }

    public function testDoesNotExecuteOrModifyPluginFile(): void
    {
        $marker = $this->directory
            . DIRECTORY_SEPARATOR
            . 'executed.txt';

        $contents = "<?php\n"
            . "/*\n"
            . " * Plugin Name: Example Plugin\n"
            . " * Version: 1.0.0\n"
            . " */\n"
            . 'file_put_contents('
            . var_export($marker, true)
            . ", 'executed');\n";

        $path = $this->writeEntry($contents);
        $before = hash_file('sha256', $path);

        $this->parser()->parse($path);

        self::assertFileDoesNotExist($marker);

        self::assertSame(
            $before,
            hash_file('sha256', $path)
        );
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
        yield 'plugin name' => [
            "<?php\n/* Version: 1.0.0 */\n",
            'Plugin Name',
        ];

        yield 'version' => [
            "<?php\n/* Plugin Name: Example Plugin */\n",
            'Version',
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function emptyRequiredHeaders(): iterable
    {
        yield 'plugin name' => [
            "<?php\n/*\n * Plugin Name:\n"
                . " * Version: 1.0.0\n */\n",
            'Plugin Name',
        ];

        yield 'version' => [
            "<?php\n/*\n * Plugin Name: Example Plugin\n"
                . " * Version:\n */\n",
            'Version',
        ];
    }

    private function parser(): WordPressPluginHeaderParser
    {
        return new WordPressPluginHeaderParser();
    }

    private function writeEntry(string $contents): string
    {
        $path = $this->directory
            . DIRECTORY_SEPARATOR
            . 'example-plugin.php';

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
