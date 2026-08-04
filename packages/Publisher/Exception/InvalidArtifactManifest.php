<?php

declare(strict_types=1);

namespace WPShop\Publisher\Exception;

use InvalidArgumentException;
use Throwable;

final class InvalidArtifactManifest extends InvalidArgumentException
{
    public static function emptyManifest(): self
    {
        return new self(
            'Publication manifest JSON cannot be empty.'
        );
    }

    public static function invalidJson(Throwable $previous): self
    {
        return new self(
            'Publication manifest must contain valid JSON.',
            0,
            $previous
        );
    }

    public static function objectRequired(): self
    {
        return new self(
            'Publication manifest JSON must contain an object.'
        );
    }

    public static function reservedArtifactProperty(): self
    {
        return new self(
            'Publication manifest cannot contain the reserved "_artifact" property.'
        );
    }

    public static function encodingFailed(Throwable $previous): self
    {
        return new self(
            'Publication manifest could not be encoded.',
            0,
            $previous
        );
    }
}
