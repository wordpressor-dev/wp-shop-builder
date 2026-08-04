<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class PublicationArtifact
{
    public function __construct(
        private string $sourcePath,
        private string $filename,
        private string $mediaType
    ) {
        $this->assertSourcePath($sourcePath);
        $this->assertFilename($filename);
        $this->assertMediaType($mediaType);
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    private function assertSourcePath(string $sourcePath): void
    {
        if (
            trim($sourcePath) === ''
            || str_contains($sourcePath, "\0")
        ) {
            throw new InvalidArgumentException(
                'Publication artifact sourcePath cannot be empty.'
            );
        }
    }

    private function assertFilename(string $filename): void
    {
        if (
            trim($filename) === ''
            || trim($filename) === '.'
            || str_contains($filename, "\0")
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
        ) {
            throw new InvalidArgumentException(
                'Publication artifact filename must be a safe filename.'
            );
        }
    }

    private function assertMediaType(string $mediaType): void
    {
        if (
            trim($mediaType) === ''
            || str_contains($mediaType, "\0")
        ) {
            throw new InvalidArgumentException(
                'Publication artifact mediaType cannot be empty.'
            );
        }
    }
}
