<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\Publisher\Exception\ThemeStructureValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\Validation\WordPressThemeStructureValidator;

final class WordPressThemeStructureValidatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-theme-structure-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testAcceptsClassicTheme(): void
    {
        $source = $this->source();

        $this->writeFile(
            $source->sourceDirectory()
                . DIRECTORY_SEPARATOR
                . 'index.php',
            "<?php\n"
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);

        self::addToAssertionCount(1);
    }

    public function testAcceptsBlockTheme(): void
    {
        $source = $this->source();

        $templatesDirectory = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'templates';

        self::assertTrue(
            mkdir($templatesDirectory, 0775, true)
        );

        $this->writeFile(
            $templatesDirectory
                . DIRECTORY_SEPARATOR
                . 'index.html',
            "<!-- wp:paragraph /-->\n"
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);

        self::addToAssertionCount(1);
    }

    public function testAcceptsThemeWithBothStructures(): void
    {
        $source = $this->source();

        $this->writeFile(
            $source->sourceDirectory()
                . DIRECTORY_SEPARATOR
                . 'index.php',
            "<?php\n"
        );

        $templatesDirectory = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'templates';

        self::assertTrue(
            mkdir($templatesDirectory, 0775, true)
        );

        $this->writeFile(
            $templatesDirectory
                . DIRECTORY_SEPARATOR
                . 'index.html',
            "<!-- wp:paragraph /-->\n"
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);

        self::addToAssertionCount(1);
    }

    public function testRejectsThemeWithoutStructuralEntryPoint(): void
    {
        $source = $this->source();

        $this->expectException(
            ThemeStructureValidationFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Theme structure "%s" must contain '
                    . 'a readable regular file "index.php" '
                    . 'or "templates/index.html".',
                $source->sourceDirectory()
            )
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);
    }

    public function testRejectsIndexPhpDirectory(): void
    {
        $source = $this->source();

        self::assertTrue(
            mkdir(
                $source->sourceDirectory()
                    . DIRECTORY_SEPARATOR
                    . 'index.php',
                0775,
                true
            )
        );

        $this->expectException(
            ThemeStructureValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme structure entry "index.php" must be '
                . 'a readable regular file.'
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);
    }

    public function testRejectsBlockIndexDirectory(): void
    {
        $source = $this->source();

        self::assertTrue(
            mkdir(
                $source->sourceDirectory()
                    . DIRECTORY_SEPARATOR
                    . 'templates'
                    . DIRECTORY_SEPARATOR
                    . 'index.html',
                0775,
                true
            )
        );

        $this->expectException(
            ThemeStructureValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme structure entry "templates/index.html" '
                . 'must be a readable regular file.'
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);
    }

    public function testRejectsSymbolicLinkEntryWhereSupported(): void
    {
        $source = $this->source();

        $target = $this->directory
            . DIRECTORY_SEPARATOR
            . 'target-index.php';

        $this->writeFile(
            $target,
            "<?php\n"
        );

        $link = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'index.php';

        if (! @symlink($target, $link)) {
            self::markTestSkipped(
                'Symbolic links are not available in this environment.'
            );
        }

        $this->expectException(
            ThemeStructureValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme structure entry "index.php" cannot be '
                . 'a symbolic link.'
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);
    }

    public function testRejectsSymbolicLinkInBlockPathWhereSupported(): void
    {
        $source = $this->source();

        $targetDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . 'real-templates';

        self::assertTrue(
            mkdir($targetDirectory, 0775, true)
        );

        $this->writeFile(
            $targetDirectory
                . DIRECTORY_SEPARATOR
                . 'index.html',
            "<!-- wp:paragraph /-->\n"
        );

        $templatesLink = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'templates';

        if (! @symlink($targetDirectory, $templatesLink)) {
            self::markTestSkipped(
                'Directory symbolic links are not available '
                    . 'in this environment.'
            );
        }

        $this->expectException(
            ThemeStructureValidationFailed::class
        );

        $this->expectExceptionMessage(
            'Theme structure entry "templates/index.html" '
                . 'cannot be a symbolic link.'
        );

        (new WordPressThemeStructureValidator())
            ->validate($source);
    }

    public function testRejectsUnreadableEntryWhereSupported(): void
    {
        $source = $this->source();

        $indexPath = $source->sourceDirectory()
            . DIRECTORY_SEPARATOR
            . 'index.php';

        $this->writeFile(
            $indexPath,
            "<?php\n"
        );

        if (! @chmod($indexPath, 0000)) {
            self::markTestSkipped(
                'File permissions cannot be changed '
                    . 'in this environment.'
            );
        }

        clearstatcache(true, $indexPath);

        if (is_readable($indexPath)) {
            @chmod($indexPath, 0644);

            self::markTestSkipped(
                'Unreadable files cannot be represented '
                    . 'in this environment.'
            );
        }

        try {
            $this->expectException(
                ThemeStructureValidationFailed::class
            );

            $this->expectExceptionMessage(
                'Theme structure entry "index.php" must be '
                    . 'a readable regular file.'
            );

            (new WordPressThemeStructureValidator())
                ->validate($source);
        } finally {
            @chmod($indexPath, 0644);
        }
    }

    private function source(): PackageSource
    {
        $sourceDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . 'source';

        self::assertTrue(
            mkdir($sourceDirectory, 0775, true)
        );

        $this->writeFile(
            $sourceDirectory
                . DIRECTORY_SEPARATOR
                . 'style.css',
            "/* Theme Name: Example Theme */\n"
        );

        return new PackageSource(
            $sourceDirectory,
            'example-theme',
            'style.css'
        );
    }

    private function writeFile(
        string $path,
        string $contents
    ): void {
        self::assertIsInt(
            file_put_contents(
                $path,
                $contents
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
                @chmod($path, 0644);
                @unlink($path);

                continue;
            }

            @rmdir($path);
        }

        @rmdir($directory);
    }
}
