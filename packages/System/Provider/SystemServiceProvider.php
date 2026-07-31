<?php

declare(strict_types=1);

namespace WPShop\System\Provider;

use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Environment\Contracts\PhpEnvironmentInterface;
use WPShop\Environment\Contracts\ServerEnvironmentInterface;
use WPShop\Environment\Contracts\WordPressEnvironmentInterface;
use WPShop\System\Contracts\SystemServiceInterface;
use WPShop\System\Service\SystemService;
use WPShop\Version\Contracts\VersionServiceInterface;

final class SystemServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $service = new SystemService(
            $this->container->get(VersionServiceInterface::class),
            $this->container->get(PhpEnvironmentInterface::class),
            $this->container->get(ServerEnvironmentInterface::class),
            $this->container->get(WordPressEnvironmentInterface::class)
        );

        $this->container->set(SystemServiceInterface::class, $service);
        $this->container->set(SystemService::class, $service);
    }
}
