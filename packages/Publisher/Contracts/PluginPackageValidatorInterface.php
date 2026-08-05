<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PluginPackageValidation;
use WPShop\Release\Release;

interface PluginPackageValidatorInterface
{
    public function validate(
        PackageSource $source,
        Release $release
    ): PluginPackageValidation;
}
