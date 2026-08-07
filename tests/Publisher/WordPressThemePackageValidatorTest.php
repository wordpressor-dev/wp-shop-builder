<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Exception\ThemeHeaderParsingFailed;
use WPShop\Publisher\Exception\ThemePackageValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;
use WPShop\Publisher\ThemeHeader;
use WPShop\Publisher\Validation\WordPressThemePackageValidator;
use WPShop\Release\Release;

final class WordPressThemePackageValidatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-theme-validator-'
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
            'example-theme',
            'style.css'
        );

        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0',
            template: 'parent-theme'
        );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->expects(self::once())
            ->method('parse')
            ->with($source->entryPath())
            ->willReturn($header);

        $validation = (
            new WordPressThemePackageValidator($parser)
        )->validate(
            $source,
            $this->release()
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
            'example-theme',
            'style.css'
        );

        $failure =
            ThemeHeaderParsingFailed::requiredHeaderMissing(
                'Theme Name',
                $source->entryPath()
            );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willThrowException($failure);

        try {
            (
                new WordPressThemePackageValidator($parser)
            )->validate(
                $source,
                $this->release()
            );

            self::fail(
                'Header parsing failure was not wrapped.'
            );
        } catch (
            ThemePackageValidationFailed $exception
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
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn(
                new ThemeHeader(
                    'Example Theme',
                    '2.0.0'
                )
            );

        $this->expectException(
            ThemePackageValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme header version "2.0.0" '
                . 'does not match Release version "1.0.0".'
        );

        (
            new WordPressThemePackageValidator($parser)
        )->validate(
            new PackageSource(
                '/tmp/example-source',
                'example-theme',
                'style.css'
            ),
            $this->release()
        );
    }

    #[DataProvider('invalidTemplateSlugs')]
    public function testRejectsInvalidTemplateSlug(
        string $slug
    ): void {
        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn(
                new ThemeHeader(
                    'Example Theme',
                    '1.0.0',
                    template: $slug
                )
            );

        $this->expectException(
            ThemePackageValidationFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Theme Template slug "%s" is invalid.',
                $slug
            )
        );

        (
            new WordPressThemePackageValidator($parser)
        )->validate(
            new PackageSource(
                '/tmp/example-source',
                'example-theme',
                'style.css'
            ),
            $this->release()
        );
    }

    public function testAcceptsThemeWithoutTemplate(): void
    {
        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn(
                new ThemeHeader(
                    'Example Theme',
                    '1.0.0'
                )
            );

        $validation = (
            new WordPressThemePackageValidator($parser)
        )->validate(
            new PackageSource(
                '/tmp/example-source',
                'example-theme',
                'style.css'
            ),
            $this->release()
        );

        self::assertNull(
            $validation->header()->template()
        );
    }

    public function testPreservesSourceAfterValidation(): void
    {
        $source = $this->source();

        $before = hash_file(
            'sha256',
            $source->entryPath()
        );

        (
            new WordPressThemePackageValidator(
                new WordPressThemeHeaderParser()
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTemplateSlugs(): iterable
    {
        yield 'uppercase' => ['Parent-Theme'];
        yield 'underscore' => ['parent_theme'];
        yield 'directory separator' => ['vendor/parent-theme'];
        yield 'backward separator' => ['vendor\\parent-theme'];
        yield 'path traversal' => ['../parent-theme'];
        yield 'leading hyphen' => ['-parent-theme'];
        yield 'trailing hyphen' => ['parent-theme-'];
        yield 'too long' => [str_repeat('a', 192)];
    }

    private function source(): PackageSource
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
                    . 'style.css',
                "/*\n"
                    . " * Theme Name: Example Theme\n"
                    . " * Version: 1.0.0\n"
                    . " * Template: parent-theme\n"
                    . " */\n"
            )
        );

        return new PackageSource(
            $sourceDirectory,
            'example-theme',
            'style.css'
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
