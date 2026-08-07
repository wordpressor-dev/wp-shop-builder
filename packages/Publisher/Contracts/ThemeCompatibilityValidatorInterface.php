<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\ThemeHeader;

interface ThemeCompatibilityValidatorInterface
{
    public function validate(ThemeHeader $header): void;
}
