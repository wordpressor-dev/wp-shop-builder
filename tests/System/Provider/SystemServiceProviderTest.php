<?php

declare(strict_types=1);

namespace WPShop\Tests\System\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Environment\Provider\EnvironmentServiceProvider;
use WPShop\System\Contracts\SystemServiceInterface;
use WPShop\System\Provider\SystemServiceProvider;
use WPShop\System\Service\SystemService;
use WPShop\Version\Provider\VersionServiceProvider;

final class SystemServiceProviderTest extends TestCase
{
    public function testRegistersSharedSystemService(): void
    {
        $container = new Container();
        (new EnvironmentServiceProvider($container))->register();
        (new VersionServiceProvider($container))->register();
        $provider = new SystemServiceProvider($container);

        $provider->register();

        self::assertInstanceOf(
            SystemService::class,
            $container->get(SystemServiceInterface::class)
        );
        self::assertSame(
            $container->get(SystemServiceInterface::class),
            $container->get(SystemService::class)
        );
    }
}
