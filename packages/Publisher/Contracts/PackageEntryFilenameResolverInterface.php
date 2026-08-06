<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Blueprint\Blueprint;

interface PackageEntryFilenameResolverInterface
{
    public function resolve(Blueprint $blueprint): string;
}
