<?php

declare(strict_types=1);

namespace WPShop\Release\Exception;

use RuntimeException;

final class ReleaseNotFound extends RuntimeException
{
    public static function byId(int $id): self
    {
        return new self(
            sprintf(
                'Release with identifier %d was not found.',
                $id
            )
        );
    }

    public static function byBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): self {
        return new self(
            sprintf(
                'Release for blueprint %d with version "%s" was not found.',
                $blueprintId,
                $version
            )
        );
    }
}
