<?php

declare(strict_types=1);

namespace WPShop\WordPress\Exception;

use InvalidArgumentException;

final class InvalidHookHandler extends InvalidArgumentException
{
    public static function forService(string $service): self
    {
        return new self(
            sprintf('Hook handler service "%s" must be callable.', $service)
        );
    }
}
