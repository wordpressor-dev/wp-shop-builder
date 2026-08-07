<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class ThemePackageValidation
{
    public function __construct(
        private ThemeHeader $header,
        private float $score
    ) {
        if (
            ! is_finite($score)
            || $score < 0.0
            || $score > 100.0
        ) {
            throw new InvalidArgumentException(
                'Theme package validation score '
                . 'must be between 0 and 100.'
            );
        }
    }

    public function header(): ThemeHeader
    {
        return $this->header;
    }

    public function score(): float
    {
        return $this->score;
    }
}
