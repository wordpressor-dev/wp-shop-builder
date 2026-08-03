<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use RuntimeException;

final class PublisherAlreadyRegistered extends RuntimeException
{
    public static function forBlueprintType(
        string $blueprintType
    ): self {
        return new self(
            sprintf(
                'A publisher is already registered for Blueprint type "%s".',
                $blueprintType
            )
        );
    }
}
