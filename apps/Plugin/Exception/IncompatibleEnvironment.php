<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Exception;

use RuntimeException;
use WPShop\App\Plugin\Compatibility\CompatibilityResult;

final class IncompatibleEnvironment extends RuntimeException
{
    public static function fromResult(
        CompatibilityResult $result
    ): self {
        return new self($result->message());
    }
}