<?php

declare(strict_types=1);

namespace WPShop\WordPress\Exception;

use LogicException;

final class WordPressFunctionUnavailable extends LogicException
{
    public static function named(string $function): self
    {
        return new self(sprintf(
            'WordPress function "%s" is unavailable. Load WordPress before using the native adapter.',
            $function
        ));
    }
}
