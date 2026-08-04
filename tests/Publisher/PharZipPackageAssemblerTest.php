<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use PharData;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Assembly\PharZipPackageAssembler;
use WPShop\Publisher\Exception\PackageAssemblyFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Release\Release;

final class PharZipPackageAssemblerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-package-assembler-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testAssemblesRecursiveZipAndPreservesSource(): void
    {
        $source = $this->packageSource();

        $artifact = $this->assembler('work')->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );

        self::assertSame(
            $this->directory
                . DIRECTORY_SEPARATOR
                . 'work'
                . DIRECTORY_SEPARATOR
                . '123e4567-e89b-12d3-a456-426614174000'
                . DIRECTORY_SEPARATOR
                . '10'
                . DIRECTORY_SEPARATOR
                . 'package.zip',
            $artifact->sourcePath()
        );

        self::assertSame(
            'package.zip',
            $artifact->filename()
        );

        self::assertSame(
            'application/zip',
            $artifact->mediaType()
        );

        self::assertFileExists($artifact->sourcePath());

        $archive = new PharData(
            $artifact->sourcePath()
        );

        self::assertTrue(
            isset(
                $archive[
                    'example-plugin/example-plugin.php'
                ]
            )
        );

        self::assertTrue(
            isset(
                $archive[
                    'example-plugin/assets/app.js'
                ]
            )
        );

        self::assertTrue(
            isset(
                $archive[
                    'example-plugin/empty'
                ]
            )
        );

        self::assertSame(
            "<?php\n/* Plugin Name: Example */\n",
            $archive[
                'example-plugin/example-plugin.php'
            ]->getContent()
        );

        self::assertSame(
            "console.log('example');\n",
            $archive[
                'example-plugin/assets/app.js'
            ]->getContent()
        );

        self::assertFileExists(
            $source->entryPath()
        );

        self::assertFileExists(
            $source->sourceDirectory()
                . DIRECTORY_SEPARATOR
                . 'assets'
                . DIRECTORY_SEPARATOR
                . 'app.js'
        );
    }

    public function testProducesDeterministicArchiveBytes(): void
    {
        $source = $this->packageSource();

        $first = $this->assembler('work-one')->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );

        $second = $this->assembler('work-two')->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );

        self::assertSame(
            hash_file('sha256', $first->sourcePath()),
            hash_file('sha256', $second->sourcePath())
        );
    }

    public function testRejectsExistingTarget(): void
    {
        $source = $this->packageSource();
        $assembler = $this->assembler('work');

        $artifact = $assembler->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );

        $this->expectException(
            PackageAssemblyFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Prepared package "%s" already exists.',
                $artifact->sourcePath()
            )
        );

        $assembler->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );
    }

    public function testRejectsMissingSourceDirectory(): void
    {
        $source = new PackageSource(
            $this->directory
                . DIRECTORY_SEPARATOR
                . 'missing',
            'example-plugin'
        );

        $this->expectException(
            PackageAssemblyFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Package assembly source directory "%s" was not found or is not readable.',
                $source->sourceDirectory()
            )
        );

        $this->assembler('work')->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );
    }

    public function testRejectsSymbolicLinkEntry(): void
    {
        $source = $this->packageSource();

        $target = $this->directory
            . DIRECTORY_SEPARATOR
            . 'linked-target.txt';

        self::assertIsInt(
            file_put_contents($target, 'linked')
        );

        $link = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'linked.txt';

        if (! @symlink($target, $link)) {
            self::markTestSkipped(
                'Symbolic links are unavailable in this environment.'
            );
        }

        $this->expectException(
            PackageAssemblyFailed::class
        );

        $this->expectExceptionMessage(
            'Package source entry "linked.txt" '
                . 'cannot be a symbolic link.'
        );

        $this->assembler('work')->assemble(
            $this->blueprint(),
            $this->release(),
            $source
        );
    }

    public function testRejectsEmptyWorkspaceRoot(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Package workspace root cannot be empty.'
        );

        new PharZipPackageAssembler('   ');
    }

    private function packageSource(): PackageSource
    {
        $sourceDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . 'source';

        $assetsDirectory = $sourceDirectory
            . DIRECTORY_SEPARATOR
            . 'assets';

        $emptyDirectory = $sourceDirectory
            . DIRECTORY_SEPARATOR
            . 'empty';

        self::assertTrue(
            mkdir($assetsDirectory, 0775, true)
        );

        self::assertTrue(
            mkdir($emptyDirectory, 0775, true)
        );

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . 'example-plugin.php',
                "<?php\n/* Plugin Name: Example */\n"
            )
        );

        self::assertIsInt(
            file_put_contents(
                $assetsDirectory
                    . DIRECTORY_SEPARATOR
                    . 'app.js',
                "console.log('example');\n"
            )
        );

        return new PackageSource(
            $sourceDirectory,
            'example-plugin'
        );
    }

    private function assembler(
        string $workspace
    ): PharZipPackageAssembler {
        return new PharZipPackageAssembler(
            $this->directory
                . DIRECTORY_SEPARATOR
                . $workspace
        );
    }

    private function blueprint(): Blueprint
    {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-plugin',
            'plugin',
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
