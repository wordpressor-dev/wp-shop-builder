<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\PackageSource;
use WPShop\Release\Release;

interface PackageSourceResolverInterface
{
    public function resolve(
        Blueprint $blueprint,
        Release $release
    ): PackageSource;
}
