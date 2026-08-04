<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class ArtifactStorageFailed extends RuntimeException
{
    public static function sourceNotFound(string $sourcePath): self
    {
        return new self(
            sprintf(
                'Publication artifact source "%s" was not found or is not readable.',
                $sourcePath
            )
        );
    }

    public static function directoryCreationFailed(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Publication artifact directory "%s" could not be created.',
                $directory
            )
        );
    }

    public static function targetAlreadyExists(
        string $storageKey
    ): self {
        return new self(
            sprintf(
                'Publication artifact "%s" already exists.',
                $storageKey
            )
        );
    }

    public static function sourceOpenFailed(
        string $sourcePath
    ): self {
        return new self(
            sprintf(
                'Publication artifact source "%s" could not be opened.',
                $sourcePath
            )
        );
    }

    public static function targetCreationFailed(
        string $storageKey
    ): self {
        return new self(
            sprintf(
                'Publication artifact "%s" could not be created.',
                $storageKey
            )
        );
    }

    public static function writeFailed(string $storageKey): self
    {
        return new self(
            sprintf(
                'Publication artifact "%s" could not be written.',
                $storageKey
            )
        );
    }

    public static function metadataInspectionFailed(
        string $storageKey
    ): self {
        return new self(
            sprintf(
                'Publication artifact "%s" metadata could not be inspected.',
                $storageKey
            )
        );
    }

    public static function sourceConsumptionFailed(
        string $sourcePath
    ): self {
        return new self(
            sprintf(
                'Publication artifact source "%s" could not be consumed.',
                $sourcePath
            )
        );
    }

    public static function deletionFailed(
        string $storageKey
    ): self {
        return new self(
            sprintf(
                'Publication artifact "%s" could not be deleted.',
                $storageKey
            )
        );
    }
}
