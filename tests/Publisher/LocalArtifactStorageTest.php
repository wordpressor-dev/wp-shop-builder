<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Exception\ArtifactStorageFailed;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\Storage\LocalArtifactStorage;
use WPShop\Publisher\StoredArtifact;
use WPShop\Release\Release;

final class LocalArtifactStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-artifacts-'
            . bin2hex(random_bytes(6));

        if (! mkdir($this->directory, 0775, true)) {
            self::fail(
                'Unable to create artifact test directory.'
            );
        }
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($this->directory);
    }

    public function testStoresPreparedArtifact(): void
    {
        $contents = 'publication package';
        $sourcePath = $this->source(
            'prepared.tmp',
            $contents
        );

        $storage = $this->storage();

        $stored = $storage->store(
            $this->blueprint(),
            $this->release(),
            new PublicationArtifact(
                $sourcePath,
                'package.zip',
                'application/zip'
            )
        );

        $expectedKey =
            '123e4567-e89b-12d3-a456-426614174000'
            . '/10/package.zip';

        $targetPath = $this->targetPath($expectedKey);

        self::assertSame(
            $expectedKey,
            $stored->storageKey()
        );

        self::assertSame(
            strlen($contents),
            $stored->size()
        );

        self::assertSame(
            hash('sha256', $contents),
            $stored->sha256()
        );

        self::assertFileDoesNotExist($sourcePath);
        self::assertFileExists($targetPath);

        self::assertSame(
            $contents,
            file_get_contents($targetPath)
        );
    }

    public function testDoesNotOverwriteExistingArtifact(): void
    {
        $storage = $this->storage();

        $storage->store(
            $this->blueprint(),
            $this->release(),
            new PublicationArtifact(
                $this->source(
                    'first.tmp',
                    'first'
                ),
                'package.zip',
                'application/zip'
            )
        );

        $secondSource = $this->source(
            'second.tmp',
            'second'
        );

        try {
            $storage->store(
                $this->blueprint(),
                $this->release(),
                new PublicationArtifact(
                    $secondSource,
                    'package.zip',
                    'application/zip'
                )
            );

            self::fail(
                'Existing artifact was overwritten.'
            );
        } catch (ArtifactStorageFailed $exception) {
            self::assertSame(
                'Publication artifact '
                    . '"123e4567-e89b-12d3-a456-426614174000'
                    . '/10/package.zip" already exists.',
                $exception->getMessage()
            );
        }

        self::assertFileExists($secondSource);

        self::assertSame(
            'first',
            file_get_contents(
                $this->targetPath(
                    '123e4567-e89b-12d3-a456-426614174000'
                    . '/10/package.zip'
                )
            )
        );
    }

    public function testRejectsMissingSource(): void
    {
        $sourcePath = $this->directory
            . DIRECTORY_SEPARATOR
            . 'missing.tmp';

        $this->expectException(
            ArtifactStorageFailed::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Publication artifact source "%s" was not found or is not readable.',
                $sourcePath
            )
        );

        $this->storage()->store(
            $this->blueprint(),
            $this->release(),
            new PublicationArtifact(
                $sourcePath,
                'package.zip',
                'application/zip'
            )
        );
    }

    public function testDeletesStoredArtifactIdempotently(): void
    {
        $storage = $this->storage();

        $stored = $storage->store(
            $this->blueprint(),
            $this->release(),
            new PublicationArtifact(
                $this->source(
                    'prepared.tmp',
                    'package'
                ),
                'package.zip',
                'application/zip'
            )
        );

        $targetPath = $this->targetPath(
            $stored->storageKey()
        );

        $storage->delete($stored);
        $storage->delete($stored);

        self::assertFileDoesNotExist($targetPath);
    }

    public function testRejectsEmptyStorageRoot(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Local artifact storage root cannot be empty.'
        );

        new LocalArtifactStorage('   ');
    }

    private function storage(): LocalArtifactStorage
    {
        return new LocalArtifactStorage(
            $this->directory
                . DIRECTORY_SEPARATOR
                . 'storage'
        );
    }

    private function source(
        string $filename,
        string $contents
    ): string {
        $path = $this->directory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (! is_int(file_put_contents($path, $contents))) {
            self::fail(
                'Unable to create prepared artifact.'
            );
        }

        return $path;
    }

    private function targetPath(string $storageKey): string
    {
        return $this->directory
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $storageKey
            );
    }

    private function blueprint(): Blueprint
    {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-blueprint',
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
}
