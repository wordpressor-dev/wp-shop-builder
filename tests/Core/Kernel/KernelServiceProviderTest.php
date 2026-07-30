<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Kernel;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ModuleInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\Core\Kernel\Kernel;

final class KernelServiceProviderTest extends TestCase
{
    public function testKernelRegistersModulesBetweenProviderRegistrationAndBoot(): void
    {
        $calls = [];
        $kernel = new Kernel();
        $kernel->addProvider(new KernelRecordingProvider($calls));
        $kernel->register(new KernelRecordingModule($calls));

        $kernel->boot();

        self::assertSame(['provider.register', 'module.boot', 'provider.boot'], $calls);
        self::assertTrue($kernel->providers()->isRegistered());
        self::assertTrue($kernel->providers()->isBooted());
    }

    public function testKernelBootDoesNotRepeatProviderLifecycle(): void
    {
        $calls = [];
        $kernel = new Kernel();
        $kernel->addProvider(new KernelRecordingProvider($calls));

        $kernel->boot();
        $kernel->boot();

        self::assertSame(['provider.register', 'provider.boot'], $calls);
    }
}

final class KernelRecordingProvider implements ServiceProviderInterface
{
    /** @var list<string> */
    private array $calls;

    /**
     * @param list<string> $calls
     */
    public function __construct(array &$calls)
    {
        $this->calls =& $calls;
    }

    public function register(): void
    {
        $this->calls[] = 'provider.register';
    }

    public function boot(KernelInterface $kernel): void
    {
        if (!$kernel instanceof Kernel) {
            throw new \RuntimeException('Unexpected Kernel implementation.');
        }

        $this->calls[] = 'provider.boot';
    }
}

final class KernelRecordingModule implements ModuleInterface
{
    /** @var list<string> */
    private array $calls;

    /**
     * @param list<string> $calls
     */
    public function __construct(array &$calls)
    {
        $this->calls =& $calls;
    }

    public function id(): string
    {
        return 'recording-module';
    }

    public function name(): string
    {
        return 'Recording module';
    }

    public function boot(): void
    {
        $this->calls[] = 'module.boot';
    }
}
