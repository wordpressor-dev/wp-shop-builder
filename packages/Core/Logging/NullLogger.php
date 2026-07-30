<?php

declare(strict_types=1);

namespace WPShop\Core\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

final class NullLogger extends AbstractLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(
        mixed $level,
        string|Stringable $message,
        array $context = []
    ): void {
    }
}
