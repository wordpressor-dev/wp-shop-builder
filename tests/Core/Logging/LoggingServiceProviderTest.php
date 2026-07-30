<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Logging;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPShop\Core\Config\ConfigRepository;
use WPShop\Core\Container\Container;
use WPShop\Core\Logging\LoggingServiceProvider;
use WPShop\Core\Logging\NullLogger;

final class LoggingServiceProviderTest extends TestCase
{
    public function testRegistersSharedPsrLogger(): void
    {
        $container = new Container();
        $provider = new LoggingServiceProvider(
            $container,
            new ConfigRepository(['logging' => ['default' => 'null']])
        );

        $provider->register();

        $logger = $container->get(LoggerInterface::class);

        self::assertInstanceOf(NullLogger::class, $logger);
        self::assertSame($logger, $container->get(LoggerInterface::class));
    }
}
