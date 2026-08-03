<?php

declare(strict_types=1);

namespace WPShop\Release\Exception;

use RuntimeException;

final class ReleasePublicationNotAllowed extends RuntimeException
{
    public static function blueprintMismatch(
        int $releaseId,
        int $actualBlueprintId,
        int $suppliedBlueprintId
    ): self {
        return new self(
            sprintf(
                'Release %d belongs to Blueprint %d, not Blueprint %d.',
                $releaseId,
                $actualBlueprintId,
                $suppliedBlueprintId
            )
        );
    }

    public static function deletedBlueprint(
        int $releaseId,
        int $blueprintId
    ): self {
        return new self(
            sprintf(
                'Release %d cannot be published because Blueprint %d is deleted.',
                $releaseId,
                $blueprintId
            )
        );
    }

    public static function alreadyPublished(
        int $releaseId
    ): self {
        return new self(
            sprintf(
                'Release %d is already published.',
                $releaseId
            )
        );
    }
}
