<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class PackageSource
{
    public function __construct(
        private string $sourceDirectory,
        private string $archiveRoot
    ) {
        $this->assertSourceDirectory($sourceDirectory);
        $this->assertArchiveRoot($archiveRoot);
    }

    public function sourceDirectory(): string
    {
        return $this->sourceDirectory;
    }

    public function archiveRoot(): string
    {
        return $this->archiveRoot;
    }

    public function entryFilename(): string
    {
        return $this->archiveRoot . '.php';
    }

    public function entryPath(): string
    {
        return $this->sourceDirectory
            . DIRECTORY_SEPARATOR
            . $this->entryFilename();
    }

    public function archiveEntry(): string
    {
        return $this->archiveRoot
            . '/'
            . $this->entryFilename();
    }

    private function assertSourceDirectory(
        string $sourceDirectory
    ): void {
        if (
            trim($sourceDirectory) === ''
            || str_contains($sourceDirectory, "\0")
        ) {
            throw new InvalidArgumentException(
                'Package source directory cannot be empty.'
            );
        }
    }

    private function assertArchiveRoot(string $archiveRoot): void
    {
        if (
            strlen($archiveRoot) > 191
            || preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{0,189}[a-z0-9])?$/D',
                $archiveRoot
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Package archive root must be a safe lowercase slug.'
            );
        }
    }
}
