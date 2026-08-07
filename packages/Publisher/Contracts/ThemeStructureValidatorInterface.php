<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\PackageSource;

interface ThemeStructureValidatorInterface
{
    public function validate(PackageSource $source): void;
}
