<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Kernel;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Kernel\Kernel;

final class KernelTest extends TestCase
{
    public function testKernelIsNotBootedInitially(): void
    {
        $kernel = new Kernel();

        self::assertFalse($kernel->isBooted());
    }

    public function testKernelCanBeBooted(): void
    {
        $kernel = new Kernel();

        $kernel->boot();

        self::assertTrue($kernel->isBooted());
    }

    public function testKernelBootIsIdempotent(): void
    {
        $kernel = new Kernel();

        $kernel->boot();
        $kernel->boot();

        self::assertTrue($kernel->isBooted());
    }
}
