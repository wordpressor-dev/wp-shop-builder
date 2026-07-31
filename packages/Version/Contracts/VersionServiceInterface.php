<?php

declare(strict_types=1);

namespace WPShop\Version\Contracts;

use WPShop\Version\DTO\VersionInformation;

interface VersionServiceInterface
{
    public function information(): VersionInformation;
}
