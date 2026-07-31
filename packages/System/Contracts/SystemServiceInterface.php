<?php

declare(strict_types=1);

namespace WPShop\System\Contracts;

use WPShop\System\DTO\SystemInformation;

interface SystemServiceInterface
{
    public function information(): SystemInformation;
}
