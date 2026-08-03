<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Release\Release;

interface ReleasePublisherServiceInterface
{
    public function publish(int $releaseId): Release;
}
