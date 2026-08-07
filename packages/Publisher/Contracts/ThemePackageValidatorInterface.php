<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\PackageSource;
use WPShop\Publisher\ThemePackageValidation;
use WPShop\Release\Release;

interface ThemePackageValidatorInterface
{
    public function validate(
        PackageSource $source,
        Release $release
    ): ThemePackageValidation;
}
