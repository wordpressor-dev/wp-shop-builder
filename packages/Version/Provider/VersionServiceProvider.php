<?php

declare(strict_types=1);

namespace WPShop\Version\Provider;

use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Version\Contracts\VersionServiceInterface;
use WPShop\Version\Service\VersionService;

final class VersionServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $service = new VersionService();

        $this->container->set(VersionServiceInterface::class, $service);
        $this->container->set(VersionService::class, $service);
    }
}
