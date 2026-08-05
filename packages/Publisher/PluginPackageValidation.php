<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class PluginPackageValidation
{
    public function __construct(
        private PluginHeader $header,
        private float $score
    ) {
        if (
            ! is_finite($score)
            || $score < 0.0
            || $score > 100.0
        ) {
            throw new InvalidArgumentException(
                'Plugin package validation score '
                . 'must be between 0 and 100.'
            );
        }
    }

    public function header(): PluginHeader
    {
        return $this->header;
    }

    public function score(): float
    {
        return $this->score;
    }
}
