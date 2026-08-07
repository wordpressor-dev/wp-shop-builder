<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Contracts\ThemeCompatibilityValidatorInterface;
use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Contracts\ThemeStructureValidatorInterface;
use WPShop\Publisher\Exception\ThemeCompatibilityValidationFailed;
use WPShop\Publisher\Exception\ThemeHeaderParsingFailed;
use WPShop\Publisher\Exception\ThemePackageValidationFailed;
use WPShop\Publisher\Exception\ThemeStructureValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;
use WPShop\Publisher\ThemeHeader;
use WPShop\Publisher\Validation\WordPressThemeCompatibilityValidator;
use WPShop\Publisher\Validation\WordPressThemePackageValidator;
use WPShop\Publisher\Validation\WordPressThemeStructureValidator;
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
            testedUpTo: '6.9',
            requiresAtLeast: '6.8',
            requiresPhp: '8.3',
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

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::once())
            ->method('validate')
            ->with($header);

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::once())
            ->method('validate')
            ->with($source);

        $validation = (
            new WordPressThemePackageValidator(
                $parser,
                $compatibilityValidator,
                $structureValidator
            )
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

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::never())
            ->method('validate');

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::never())
            ->method('validate');

        try {
            (
                new WordPressThemePackageValidator(
                    $parser,
                    $compatibilityValidator,
                    $structureValidator
                )
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

    public function testRejectsVersionMismatchBeforeCompatibility(): void
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

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::never())
            ->method('validate');

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::never())
            ->method('validate');

        $this->expectException(
            ThemePackageValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme header version "2.0.0" '
                . 'does not match Release version "1.0.0".'
        );

        (
            new WordPressThemePackageValidator(
                $parser,
                $compatibilityValidator,
                $structureValidator
            )
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
    public function testRejectsInvalidTemplateSlugBeforeCompatibility(
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

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::never())
            ->method('validate');

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::never())
            ->method('validate');

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
            new WordPressThemePackageValidator(
                $parser,
                $compatibilityValidator,
                $structureValidator
            )
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
        $source = new PackageSource(
            '/tmp/example-source',
            'example-theme',
            'style.css'
        );

        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0'
        );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn($header);

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::once())
            ->method('validate')
            ->with($header);

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::once())
            ->method('validate')
            ->with($source);

        $validation = (
            new WordPressThemePackageValidator(
                $parser,
                $compatibilityValidator,
                $structureValidator
            )
        )->validate(
            $source,
            $this->release()
        );

        self::assertNull(
            $validation->header()->template()
        );
    }

    public function testValidatesCompatibilityBeforeStructure(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-theme',
            'style.css'
        );

        /** @var list<string> $events */
        $events = [];

        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0',
            testedUpTo: '6.9',
            requiresAtLeast: '6.8',
            requiresPhp: '8.3',
            template: 'parent-theme'
        );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturnCallback(
                static function () use (
                    &$events,
                    $header
                ): ThemeHeader {
                    $events[] = 'header';

                    return $header;
                }
            );

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->method('validate')
            ->with($header)
            ->willReturnCallback(
                static function () use (&$events): void {
                    $events[] = 'compatibility';
                }
            );

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->method('validate')
            ->with($source)
            ->willReturnCallback(
                static function () use (&$events): void {
                    $events[] = 'structure';
                }
            );

        (
            new WordPressThemePackageValidator(
                $parser,
                $compatibilityValidator,
                $structureValidator
            )
        )->validate(
            $source,
            $this->release()
        );

        self::assertSame(
            [
                'header',
                'compatibility',
                'structure',
            ],
            $events
        );
    }

    public function testWrapsCompatibilityFailureBeforeStructure(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-theme',
            'style.css'
        );

        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0',
            requiresPhp: 'eight.three'
        );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn($header);

        $failure =
            ThemeCompatibilityValidationFailed::invalidVersion(
                'Requires PHP',
                'eight.three'
            );

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::once())
            ->method('validate')
            ->with($header)
            ->willThrowException($failure);

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->expects(self::never())
            ->method('validate');

        try {
            (
                new WordPressThemePackageValidator(
                    $parser,
                    $compatibilityValidator,
                    $structureValidator
                )
            )->validate(
                $source,
                $this->release()
            );

            self::fail(
                'Compatibility validation failure was not wrapped.'
            );
        } catch (
            ThemePackageValidationFailed $exception
        ) {
            self::assertSame(
                $failure,
                $exception->getPrevious()
            );

            self::assertSame(
                sprintf(
                    'Theme package compatibility metadata "%s" '
                        . 'is invalid. %s',
                    $source->entryPath(),
                    $failure->getMessage()
                ),
                $exception->getMessage()
            );
        }
    }

    public function testWrapsStructureValidationFailure(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-theme',
            'style.css'
        );

        $header = new ThemeHeader(
            'Example Theme',
            '1.0.0'
        );

        $parser = $this->createMock(
            ThemeHeaderParserInterface::class
        );

        $parser
            ->method('parse')
            ->willReturn($header);

        $compatibilityValidator = $this->createMock(
            ThemeCompatibilityValidatorInterface::class
        );

        $compatibilityValidator
            ->expects(self::once())
            ->method('validate')
            ->with($header);

        $failure =
            ThemeStructureValidationFailed::missingEntryPoint(
                $source->sourceDirectory()
            );

        $structureValidator = $this->createMock(
            ThemeStructureValidatorInterface::class
        );

        $structureValidator
            ->method('validate')
            ->with($source)
            ->willThrowException($failure);

        try {
            (
                new WordPressThemePackageValidator(
                    $parser,
                    $compatibilityValidator,
                    $structureValidator
                )
            )->validate(
                $source,
                $this->release()
            );

            self::fail(
                'Structure validation failure was not wrapped.'
            );
        } catch (
            ThemePackageValidationFailed $exception
        ) {
            self::assertSame(
                $failure,
                $exception->getPrevious()
            );

            self::assertSame(
                'Theme package structure "/tmp/example-source" '
                    . 'is invalid. '
                    . $failure->getMessage(),
                $exception->getMessage()
            );
        }
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
                new WordPressThemeHeaderParser(),
                new WordPressThemeCompatibilityValidator(),
                new WordPressThemeStructureValidator()
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
                    . " * Tested up to: 6.9\n"
                    . " * Requires at least: 6.8\n"
                    . " * Requires PHP: 8.3\n"
                    . " * Template: parent-theme\n"
                    . " */\n"
            )
        );

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . 'index.php',
                "<?php\n"
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
