<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Release\Release;

interface PackageAssemblerInterface
{
    public function assemble(
        Blueprint $blueprint,
        Release $release,
        PackageSource $source
    ): PublicationArtifact;
}
