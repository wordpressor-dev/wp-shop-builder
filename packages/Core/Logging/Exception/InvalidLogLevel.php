<?php

declare(strict_types=1);

namespace WPShop\Core\Logging\Exception;

use InvalidArgumentException;

final class InvalidLogLevel extends InvalidArgumentException
{
    public static function forLevel(string $level): self
    {
        return new self(sprintf('Unsupported log level "%s".', $level));
    }
}
