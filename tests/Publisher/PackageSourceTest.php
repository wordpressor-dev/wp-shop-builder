<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PackageSource;

final class PackageSourceTest extends TestCase
{
    #[DataProvider('packageSources')]
    public function testExposesValidatedPackageSource(
        string $archiveRoot,
        string $entryFilename,
        string $archiveEntry
    ): void {
        $source = new PackageSource(
            '/tmp/example-source',
            $archiveRoot,
            $entryFilename
        );

        self::assertSame(
            '/tmp/example-source',
            $source->sourceDirectory()
        );

        self::assertSame(
            $archiveRoot,
            $source->archiveRoot()
        );

        self::assertSame(
            $entryFilename,
            $source->entryFilename()
        );

        self::assertSame(
            '/tmp/example-source'
                . DIRECTORY_SEPARATOR
                . $entryFilename,
            $source->entryPath()
        );

        self::assertSame(
            $archiveEntry,
            $source->archiveEntry()
        );
    }

    #[DataProvider('emptySourceDirectories')]
    public function testRejectsEmptySourceDirectory(
        string $directory
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Package source directory cannot be empty.'
        );

        new PackageSource(
            $directory,
            'example-plugin',
            'example-plugin.php'
        );
    }

    #[DataProvider('unsafeArchiveRoots')]
    public function testRejectsUnsafeArchiveRoot(
        string $archiveRoot
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Package archive root must be a safe lowercase slug.'
        );

        new PackageSource(
            '/tmp/example-source',
            $archiveRoot,
            'example-plugin.php'
        );
    }

    #[DataProvider('unsafeEntryFilenames')]
    public function testRejectsUnsafeEntryFilename(
        string $entryFilename
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Package entry filename must be a safe basename.'
        );

        new PackageSource(
            '/tmp/example-source',
            'example-package',
            $entryFilename
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function packageSources(): iterable
    {
        yield 'plugin' => [
            'example-plugin',
            'example-plugin.php',
            'example-plugin/example-plugin.php',
        ];

        yield 'theme' => [
            'example-theme',
            'style.css',
            'example-theme/style.css',
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptySourceDirectories(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'null byte' => ["source\0directory"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeArchiveRoots(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'uppercase' => ['Example-Plugin'];
        yield 'underscore' => ['example_plugin'];
        yield 'forward directory' => ['nested/plugin'];
        yield 'backward directory' => ['nested\\plugin'];
        yield 'leading hyphen' => ['-example'];
        yield 'trailing hyphen' => ['example-'];
        yield 'too long' => [str_repeat('a', 192)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeEntryFilenames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'leading whitespace' => [' style.css'];
        yield 'trailing whitespace' => ['style.css '];
        yield 'current directory' => ['.'];
        yield 'parent directory' => ['..'];
        yield 'forward directory' => ['nested/style.css'];
        yield 'backward directory' => ['nested\\style.css'];
        yield 'path traversal' => ['../style.css'];
        yield 'embedded traversal' => ['style..css'];
        yield 'null byte' => ["style\0.css"];
    }
}
