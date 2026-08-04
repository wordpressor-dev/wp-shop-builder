<?php

declare(strict_types=1);

namespace WPShop\Publisher\Storage;

use InvalidArgumentException;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\ArtifactStorageInterface;
use WPShop\Publisher\Exception\ArtifactStorageFailed;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\StoredArtifact;
use WPShop\Release\Release;

final readonly class LocalArtifactStorage implements
    ArtifactStorageInterface
{
    private string $root;

    public function __construct(string $root)
    {
        $normalizedRoot = rtrim(
            trim($root),
            '/\\'
        );

        if ($normalizedRoot === '') {
            throw new InvalidArgumentException(
                'Local artifact storage root cannot be empty.'
            );
        }

        $this->root = $normalizedRoot;
    }

    public function store(
        Blueprint $blueprint,
        Release $release,
        PublicationArtifact $artifact
    ): StoredArtifact {
        $storageKey = sprintf(
            '%s/%d/%s',
            $blueprint->uuid(),
            $release->id(),
            $artifact->filename()
        );

        $sourcePath = $artifact->sourcePath();
        $targetPath = $this->pathFor($storageKey);

        if (
            ! is_file($sourcePath)
            || ! is_readable($sourcePath)
        ) {
            throw ArtifactStorageFailed::sourceNotFound(
                $sourcePath
            );
        }

        if (file_exists($targetPath)) {
            throw ArtifactStorageFailed::targetAlreadyExists(
                $storageKey
            );
        }

        $this->createDirectory(
            dirname($targetPath)
        );

        $source = @fopen($sourcePath, 'rb');

        if (! is_resource($source)) {
            throw ArtifactStorageFailed::sourceOpenFailed(
                $sourcePath
            );
        }

        $target = @fopen($targetPath, 'x+b');

        if (! is_resource($target)) {
            fclose($source);

            if (file_exists($targetPath)) {
                throw ArtifactStorageFailed::targetAlreadyExists(
                    $storageKey
                );
            }

            throw ArtifactStorageFailed::targetCreationFailed(
                $storageKey
            );
        }

        $bytes = @stream_copy_to_stream(
            $source,
            $target
        );

        $flushed = @fflush($target);

        fclose($source);
        fclose($target);

        if (
            ! is_int($bytes)
            || ! $flushed
        ) {
            $this->removePartialTarget($targetPath);

            throw ArtifactStorageFailed::writeFailed(
                $storageKey
            );
        }

        $size = @filesize($targetPath);
        $sha256 = @hash_file(
            'sha256',
            $targetPath
        );

        if (
            ! is_int($size)
            || ! is_string($sha256)
        ) {
            $this->removePartialTarget($targetPath);

            throw ArtifactStorageFailed::metadataInspectionFailed(
                $storageKey
            );
        }

        if (! @unlink($sourcePath)) {
            $this->removePartialTarget($targetPath);

            throw ArtifactStorageFailed::sourceConsumptionFailed(
                $sourcePath
            );
        }

        return new StoredArtifact(
            $storageKey,
            $artifact->filename(),
            $artifact->mediaType(),
            $size,
            $sha256
        );
    }

    public function delete(StoredArtifact $artifact): void
    {
        $targetPath = $this->pathFor(
            $artifact->storageKey()
        );

        if (! file_exists($targetPath)) {
            return;
        }

        if (
            ! is_file($targetPath)
            || ! @unlink($targetPath)
        ) {
            throw ArtifactStorageFailed::deletionFailed(
                $artifact->storageKey()
            );
        }
    }

    private function createDirectory(string $directory): void
    {
        if (
            ! is_dir($directory)
            && ! @mkdir(
                $directory,
                0775,
                true
            )
            && ! is_dir($directory)
        ) {
            throw ArtifactStorageFailed::directoryCreationFailed(
                $directory
            );
        }
    }

    private function pathFor(string $storageKey): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $storageKey
            );
    }

    private function removePartialTarget(string $targetPath): void
    {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
    }
}
