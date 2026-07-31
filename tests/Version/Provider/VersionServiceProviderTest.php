<?php

declare(strict_types=1);

namespace WPShop\Tests\Version\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Version\Contracts\VersionServiceInterface;
use WPShop\Version\Provider\VersionServiceProvider;
use WPShop\Version\Service\VersionService;

final class VersionServiceProviderTest extends TestCase
{
    public function testRegistersSharedVersionService(): void
    {
        $container = new Container();
        $provider = new VersionServiceProvider($container);

        $provider->register();

        self::assertInstanceOf(
            VersionService::class,
            $container->get(VersionServiceInterface::class)
        );
        self::assertSame(
            $container->get(VersionServiceInterface::class),
            $container->get(VersionService::class)
        );
    }
}
