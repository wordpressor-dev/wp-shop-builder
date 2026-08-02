<?php

declare(strict_types=1);

namespace WPShop\Manifest\Exception;

use RuntimeException;

final class ManifestNotFound extends RuntimeException
{
    public static function byId(int $id): self
    {
        return new self(
            sprintf(
                'Manifest with identifier %d was not found.',
                $id
            )
        );
    }

    public static function byReleaseId(
        int $releaseId
    ): self {
        return new self(
            sprintf(
                'Manifest for release %d was not found.',
                $releaseId
            )
        );
    }
}
