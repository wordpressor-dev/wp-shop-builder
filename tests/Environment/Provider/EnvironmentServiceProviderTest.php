<?php

declare(strict_types=1);

namespace WPShop\Tests\Environment\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Environment\Contracts\PhpEnvironmentInterface;
use WPShop\Environment\Contracts\ServerEnvironmentInterface;
use WPShop\Environment\Contracts\WordPressEnvironmentInterface;
use WPShop\Environment\PhpEnvironment;
use WPShop\Environment\Provider\EnvironmentServiceProvider;
use WPShop\Environment\ServerEnvironment;
use WPShop\Environment\WordPressEnvironment;

final class EnvironmentServiceProviderTest extends TestCase
{
    public function testRegistersSharedEnvironmentServices(): void
    {
        $container = new Container();
        $provider = new EnvironmentServiceProvider($container);

        $provider->register();

        self::assertInstanceOf(
            PhpEnvironment::class,
            $container->get(PhpEnvironmentInterface::class)
        );
        self::assertSame(
            $container->get(PhpEnvironmentInterface::class),
            $container->get(PhpEnvironment::class)
        );
        self::assertInstanceOf(
            ServerEnvironment::class,
            $container->get(ServerEnvironmentInterface::class)
        );
        self::assertSame(
            $container->get(ServerEnvironmentInterface::class),
            $container->get(ServerEnvironment::class)
        );
        self::assertInstanceOf(
            WordPressEnvironment::class,
            $container->get(WordPressEnvironmentInterface::class)
        );
        self::assertSame(
            $container->get(WordPressEnvironmentInterface::class),
            $container->get(WordPressEnvironment::class)
        );
    }
}
