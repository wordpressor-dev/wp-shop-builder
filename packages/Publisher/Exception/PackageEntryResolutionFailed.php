<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class PackageEntryResolutionFailed extends RuntimeException
{
    public static function unsupportedBlueprintType(
        string $blueprintType
    ): self {
        return new self(
            sprintf(
                'Package entry filename cannot be resolved '
                    . 'for Blueprint type "%s".',
                $blueprintType
            )
        );
    }
}
