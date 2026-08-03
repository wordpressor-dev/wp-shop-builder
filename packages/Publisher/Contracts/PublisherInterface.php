<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\PublicationResult;
use WPShop\Release\Release;

interface PublisherInterface
{
    public function publish(
        Blueprint $blueprint,
        Release $release
    ): PublicationResult;
}
