<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class StoredArtifact
{
    public function __construct(
        private string $storageKey,
        private string $filename,
        private string $mediaType,
        private int $size,
        private string $sha256
    ) {
        $this->assertStorageKey($storageKey);
        $this->assertFilename($filename, $storageKey);
        $this->assertMediaType($mediaType);
        $this->assertSize($size);
        $this->assertSha256($sha256);
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    private function assertStorageKey(string $storageKey): void
    {
        $segments = explode('/', $storageKey);

        if (
            trim($storageKey) === ''
            || str_starts_with($storageKey, '/')
            || str_ends_with($storageKey, '/')
            || str_contains($storageKey, '\\')
            || str_contains($storageKey, "\0")
        ) {
            throw new InvalidArgumentException(
                'Stored artifact storageKey must be a safe relative path.'
            );
        }

        foreach ($segments as $segment) {
            if (
                in_array($segment, ['', '.', '..'], true)
            ) {
                throw new InvalidArgumentException(
                    'Stored artifact storageKey must be a safe relative path.'
                );
            }
        }
    }

    private function assertFilename(
        string $filename,
        string $storageKey
    ): void {
        if (
            trim($filename) === ''
            || str_contains($filename, "\0")
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
        ) {
            throw new InvalidArgumentException(
                'Stored artifact filename must be a safe filename.'
            );
        }

        $segments = explode('/', $storageKey);

        if (end($segments) !== $filename) {
            throw new InvalidArgumentException(
                'Stored artifact filename must match its storageKey.'
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
                'Stored artifact mediaType cannot be empty.'
            );
        }
    }

    private function assertSize(int $size): void
    {
        if ($size < 0) {
            throw new InvalidArgumentException(
                'Stored artifact size cannot be negative.'
            );
        }
    }

    private function assertSha256(string $sha256): void
    {
        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $sha256
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Stored artifact sha256 must be a lowercase SHA-256 checksum.'
            );
        }
    }
}
