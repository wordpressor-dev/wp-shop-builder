<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Contracts\PluginHeaderParserInterface;
use WPShop\Publisher\Exception\PluginHeaderParsingFailed;
use WPShop\Publisher\Exception\PluginPackageValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\Parser\WordPressPluginHeaderParser;
use WPShop\Publisher\PluginHeader;
use WPShop\Publisher\Validation\WordPressPluginPackageValidator;
use WPShop\Release\Release;

final class WordPressPluginPackageValidatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-plugin-validator-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testReturnsHeaderAndPerfectScore(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-plugin',
            'example-plugin.php'
        );

        $release = $this->release();

        $header = new PluginHeader(
            'Example Plugin',
            '1.0.0',
            requiredPlugins: [
                'dependency-one',
                'dependency-two',
            ]
        );

        $parser = $this->createMock(
            PluginHeaderParserInterface::class
        );

        $parser
            ->expects(self::once())
            ->method('parse')
            ->with($source->entryPath())
            ->willReturn($header);

        $validation = (
            new WordPressPluginPackageValidator($parser)
        )->validate(
            $source,
            $release
        );

        self::assertSame(
            $header,
            $validation->header()
        );

        self::assertSame(
            100.0,
            $validation->score()
        );
    }

    public function testWrapsHeaderParsingFailure(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-plugin',
            'example-plugin.php'
        );

        $failure =
            PluginHeaderParsingFailed::requiredHeaderMissing(
                'Plugin Name',
                $source->entryPath()
            );

        $parser = $this->createMock(
            PluginHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willThrowException($failure);

        try {
            (
                new WordPressPluginPackageValidator($parser)
            )->validate(
                $source,
                $this->release()
            );

            self::fail(
                'Header parsing failure was not wrapped.'
            );
        } catch (
            PluginPackageValidationFailed $exception
        ) {
            self::assertSame(
                $failure,
                $exception->getPrevious()
            );

            self::assertStringContainsString(
                $failure->getMessage(),
                $exception->getMessage()
            );
        }
    }

    public function testRejectsVersionMismatch(): void
    {
        $parser = $this->createMock(
            PluginHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn(
                new PluginHeader(
                    'Example Plugin',
                    '2.0.0'
                )
            );

        $this->expectException(
            PluginPackageValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Plugin header version "2.0.0" '
                . 'does not match Release version "1.0.0".'
        );

        (
            new WordPressPluginPackageValidator($parser)
        )->validate(
            new PackageSource(
                '/tmp/example-source',
                'example-plugin',
                'example-plugin.php'
            ),
            $this->release()
        );
    }

    #[DataProvider('invalidPluginSlugs')]
    public function testRejectsInvalidRequiredPluginSlug(
        string $slug
    ): void {
        $parser = $this->createMock(
            PluginHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn(
                new PluginHeader(
                    'Example Plugin',
                    '1.0.0',
                    requiredPlugins: [$slug]
                )
            );

        $this->expectException(
            PluginPackageValidationFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Required plugin slug "%s" is invalid.',
                $slug
            )
        );

        (
            new WordPressPluginPackageValidator($parser)
        )->validate(
            new PackageSource(
                '/tmp/example-source',
                'example-plugin',
                'example-plugin.php'
            ),
            $this->release()
        );
    }

    public function testPreservesSourceAfterSuccess(): void
    {
        $source = $this->source(
            '1.0.0'
        );

        $before = hash_file(
            'sha256',
            $source->entryPath()
        );

        (
            new WordPressPluginPackageValidator(
                new WordPressPluginHeaderParser()
            )
        )->validate(
            $source,
            $this->release()
        );

        self::assertSame(
            $before,
            hash_file(
                'sha256',
                $source->entryPath()
            )
        );
    }

    public function testPreservesSourceAfterFailure(): void
    {
        $source = $this->source(
            '2.0.0'
        );

        $before = hash_file(
            'sha256',
            $source->entryPath()
        );

        try {
            (
                new WordPressPluginPackageValidator(
                    new WordPressPluginHeaderParser()
                )
            )->validate(
                $source,
                $this->release()
            );

            self::fail(
                'Version mismatch was not raised.'
            );
        } catch (
            PluginPackageValidationFailed
        ) {
            self::assertSame(
                $before,
                hash_file(
                    'sha256',
                    $source->entryPath()
                )
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPluginSlugs(): iterable
    {
        yield 'uppercase' => ['Dependency'];
        yield 'underscore' => ['dependency_plugin'];
        yield 'directory separator' => ['vendor/dependency'];
        yield 'leading hyphen' => ['-dependency'];
        yield 'trailing hyphen' => ['dependency-'];
        yield 'too long' => [str_repeat('a', 192)];
    }

    private function source(string $version): PackageSource
    {
        $sourceDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . 'source';

        self::assertTrue(
            mkdir($sourceDirectory, 0775, true)
        );

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . 'example-plugin.php',
                "<?php\n"
                    . "/*\n"
                    . " * Plugin Name: Example Plugin\n"
                    . sprintf(
                        " * Version: %s\n",
                        $version
                    )
                    . " */\n"
            )
        );

        return new PackageSource(
            $sourceDirectory,
            'example-plugin',
            'example-plugin.php'
        );
    }

    private function release(): Release
    {
        return new Release(
            10,
            5,
            '1.0.0',
            'draft',
            null,
            false,
            null,
            new DateTimeImmutable(
                '2026-08-03 10:00:00'
            )
        );
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
