<?php

declare(strict_types=1);

namespace WPShop\Tests\Environment;

use PHPUnit\Framework\TestCase;
use WPShop\Environment\PhpEnvironment;

final class PhpEnvironmentTest extends TestCase
{
    public function testExposesPhpRuntimeInformation(): void
    {
        $environment = new PhpEnvironment();

        self::assertSame(PHP_VERSION, $environment->version());
        self::assertNotSame('', $environment->memoryLimit());
        self::assertNotSame('', $environment->uploadMaxFilesize());
        self::assertNotSame('', $environment->postMaxSize());
        self::assertGreaterThanOrEqual(0, $environment->maxExecutionTime());
        self::assertContains('Core', $environment->extensions());
    }
}
