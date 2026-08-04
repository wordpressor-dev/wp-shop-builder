<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PackageSource;

final class PackageSourceTest extends TestCase
{
    public function testExposesValidatedPackageSource(): void
    {
        $source = new PackageSource(
            '/tmp/example-source',
            'example-plugin'
        );

        self::assertSame(
            '/tmp/example-source',
            $source->sourceDirectory()
        );

        self::assertSame(
            'example-plugin',
            $source->archiveRoot()
        );

        self::assertSame(
            'example-plugin.php',
            $source->entryFilename()
        );

        self::assertSame(
            '/tmp/example-source'
                . DIRECTORY_SEPARATOR
                . 'example-plugin.php',
            $source->entryPath()
        );

        self::assertSame(
            'example-plugin/example-plugin.php',
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
            'example-plugin'
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
            $archiveRoot
        );
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
}
