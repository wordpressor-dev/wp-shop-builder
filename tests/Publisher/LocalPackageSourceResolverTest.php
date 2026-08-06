<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Exception\PackageSourceResolutionFailed;
use WPShop\Publisher\Resolution\WordPressPackageEntryFilenameResolver;
use WPShop\Publisher\Source\LocalPackageSourceResolver;
use WPShop\Release\Release;

final class LocalPackageSourceResolverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-source-resolver-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[DataProvider('supportedSources')]
    public function testResolvesDeterministicLocalSource(
        string $type,
        string $slug,
        string $entryFilename,
        string $archiveEntry
    ): void {
        $sourceDirectory = $this->sourceDirectory();

        self::assertTrue(
            mkdir($sourceDirectory, 0775, true)
        );

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . $entryFilename,
                $type === 'plugin'
                    ? "<?php\n"
                    : "/* Theme Name: Example */\n"
            )
        );

        $source = $this->resolver()->resolve(
            $this->blueprint($slug, $type),
            $this->release()
        );

        self::assertSame(
            $sourceDirectory,
            $source->sourceDirectory()
        );

        self::assertSame(
            $slug,
            $source->archiveRoot()
        );

        self::assertSame(
            $entryFilename,
            $source->entryFilename()
        );

        self::assertSame(
            $archiveEntry,
            $source->archiveEntry()
        );
    }

    public function testRejectsEmptySourceRoot(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Local package source root cannot be empty.'
        );

        new LocalPackageSourceResolver(
            '   ',
            new WordPressPackageEntryFilenameResolver()
        );
    }

    public function testRejectsMissingSourceDirectory(): void
    {
        $this->expectException(
            PackageSourceResolutionFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Package source directory "%s" was not found or is not readable.',
                $this->sourceDirectory()
            )
        );

        $this->resolver()->resolve(
            $this->blueprint(),
            $this->release()
        );
    }

    #[DataProvider('missingEntries')]
    public function testRejectsMissingEntryFile(
        string $type,
        string $slug,
        string $entryFilename
    ): void {
        self::assertTrue(
            mkdir($this->sourceDirectory(), 0775, true)
        );

        $entryPath = $this->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . $entryFilename;

        $this->expectException(
            PackageSourceResolutionFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Package entry file "%s" was not found or is not readable.',
                $entryPath
            )
        );

        $this->resolver()->resolve(
            $this->blueprint($slug, $type),
            $this->release()
        );
    }

    public function testRejectsUnsafeBlueprintSlug(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Package archive root must be a safe lowercase slug.'
        );

        $this->resolver()->resolve(
            $this->blueprint('Unsafe Plugin'),
            $this->release()
        );
    }

    #[DataProvider('symbolicLinkEntries')]
    public function testRejectsSymbolicLinkEntryFile(
        string $type,
        string $slug,
        string $entryFilename
    ): void {
        $sourceDirectory = $this->sourceDirectory();

        self::assertTrue(
            mkdir($sourceDirectory, 0775, true)
        );

        $target = $this->directory
            . DIRECTORY_SEPARATOR
            . 'real-entry';

        self::assertIsInt(
            file_put_contents($target, 'entry')
        );

        $link = $sourceDirectory
            . DIRECTORY_SEPARATOR
            . $entryFilename;

        if (! @symlink($target, $link)) {
            self::markTestSkipped(
                'Symbolic links are unavailable in this environment.'
            );
        }

        $this->expectException(
            PackageSourceResolutionFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Package entry file "%s" cannot be a symbolic link.',
                $link
            )
        );

        $this->resolver()->resolve(
            $this->blueprint($slug, $type),
            $this->release()
        );
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function supportedSources(): iterable
    {
        yield 'plugin' => [
            'plugin',
            'example-plugin',
            'example-plugin.php',
            'example-plugin/example-plugin.php',
        ];

        yield 'theme' => [
            'theme',
            'example-theme',
            'style.css',
            'example-theme/style.css',
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function missingEntries(): iterable
    {
        yield 'plugin' => [
            'plugin',
            'example-plugin',
            'example-plugin.php',
        ];

        yield 'theme' => [
            'theme',
            'example-theme',
            'style.css',
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function symbolicLinkEntries(): iterable
    {
        yield 'plugin' => [
            'plugin',
            'example-plugin',
            'example-plugin.php',
        ];

        yield 'theme' => [
            'theme',
            'example-theme',
            'style.css',
        ];
    }

    private function resolver(): LocalPackageSourceResolver
    {
        return new LocalPackageSourceResolver(
            $this->directory
                . DIRECTORY_SEPARATOR
                . 'sources',
            new WordPressPackageEntryFilenameResolver()
        );
    }

    private function sourceDirectory(): string
    {
        return $this->directory
            . DIRECTORY_SEPARATOR
            . 'sources'
            . DIRECTORY_SEPARATOR
            . '123e4567-e89b-12d3-a456-426614174000'
            . DIRECTORY_SEPARATOR
            . '10';
    }

    private function blueprint(
        string $slug = 'example-plugin',
        string $type = 'plugin'
    ): Blueprint {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            $slug,
            $type,
            null,
            null,
            null,
            'active',
            'draft',
            new DateTimeImmutable(
                '2026-08-01 10:00:00'
            ),
            new DateTimeImmutable(
                '2026-08-02 10:00:00'
            ),
            null
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

            if ($entry->isLink() || $entry->isFile()) {
                @unlink($path);

                continue;
            }

            @rmdir($path);
        }

        @rmdir($directory);
    }
}
