<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;

interface ReleasePublicationServiceInterface
{
    public function publish(
        ReleasePublicationData $data
    ): Release;
}
