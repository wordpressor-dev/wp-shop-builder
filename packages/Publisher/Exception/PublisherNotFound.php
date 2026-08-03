<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class PublisherNotFound extends RuntimeException
{
    public static function forBlueprintType(
        string $blueprintType
    ): self {
        return new self(
            sprintf(
                'No publisher is registered for Blueprint type "%s".',
                $blueprintType
            )
        );
    }
}
