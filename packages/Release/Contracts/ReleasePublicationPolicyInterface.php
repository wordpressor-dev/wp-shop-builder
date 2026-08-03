<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Release\Release;

interface ReleasePublicationPolicyInterface
{
    public function assertCanPublish(
        Blueprint $blueprint,
        Release $release
    ): void;
}
