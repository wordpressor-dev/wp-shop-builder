<?php

declare(strict_types=1);

namespace WPShop\Tests\Environment;

use PHPUnit\Framework\TestCase;
use WPShop\Environment\ServerEnvironment;

final class ServerEnvironmentTest extends TestCase
{
    public function testExposesServerRuntimeInformation(): void
    {
        $environment = new ServerEnvironment();

        self::assertNotSame('', $environment->software());
        self::assertSame(PHP_SAPI, $environment->phpSapi());
        self::assertSame(PHP_OS_FAMILY, $environment->operatingSystem());
    }
}
