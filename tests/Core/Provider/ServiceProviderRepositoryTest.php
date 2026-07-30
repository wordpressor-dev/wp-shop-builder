<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Provider;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\Core\Kernel\Kernel;
use WPShop\Core\Provider\Exception\ServiceProviderAlreadyRegistered;
use WPShop\Core\Provider\Exception\ServiceProvidersNotRegistered;
use WPShop\Core\Provider\ServiceProviderRepository;

final class ServiceProviderRepositoryTest extends TestCase
{
    public function testProviderCanBeAddedAndRetrieved(): void
    {
        $repository = new ServiceProviderRepository();
        $provider = new RecordingServiceProvider();

        $repository->add($provider);

        self::assertTrue($repository->has(RecordingServiceProvider::class));
        self::assertSame([$provider], $repository->all());
    }

    public function testDuplicateProviderClassCannotBeAdded(): void
    {
        $repository = new ServiceProviderRepository();
        $repository->add(new RecordingServiceProvider());

        $this->expectException(ServiceProviderAlreadyRegistered::class);

        $repository->add(new RecordingServiceProvider());
    }

    public function testProvidersAreRegisteredInInsertionOrder(): void
    {
        $calls = [];
        $repository = new ServiceProviderRepository();
        $repository->add(new RecordingServiceProvider('first', $calls));
        $repository->add(new SecondaryRecordingServiceProvider('second', $calls));

        $repository->registerAll();

        self::assertSame(['register:first', 'register:second'], $calls);
        self::assertTrue($repository->isRegistered());
    }

    public function testRegisterAllIsIdempotent(): void
    {
        $provider = new RecordingServiceProvider();
        $repository = new ServiceProviderRepository();
        $repository->add($provider);

        $repository->registerAll();
        $repository->registerAll();

        self::assertSame(1, $provider->registerCalls);
    }

    public function testBootRequiresRegistrationFirst(): void
    {
        $repository = new ServiceProviderRepository();
        $repository->add(new RecordingServiceProvider());

        $this->expectException(ServiceProvidersNotRegistered::class);

        $repository->bootAll(new Kernel());
    }

    public function testProvidersAreBootedInInsertionOrder(): void
    {
        $calls = [];
        $repository = new ServiceProviderRepository();
        $repository->add(new RecordingServiceProvider('first', $calls));
        $repository->add(new SecondaryRecordingServiceProvider('second', $calls));
        $kernel = new Kernel();

        $repository->registerAll();
        $repository->bootAll($kernel);

        self::assertSame(
            ['register:first', 'register:second', 'boot:first', 'boot:second'],
            $calls
        );
        self::assertTrue($repository->isBooted());
    }

    public function testBootAllIsIdempotent(): void
    {
        $provider = new RecordingServiceProvider();
        $repository = new ServiceProviderRepository();
        $repository->add($provider);
        $kernel = new Kernel();

        $repository->registerAll();
        $repository->bootAll($kernel);
        $repository->bootAll($kernel);

        self::assertSame(1, $provider->bootCalls);
    }

    public function testProviderCannotBeAddedAfterRegistration(): void
    {
        $repository = new ServiceProviderRepository();
        $repository->registerAll();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Service providers cannot be added after registration has started.');

        $repository->add(new RecordingServiceProvider());
    }
}

class RecordingServiceProvider implements ServiceProviderInterface
{
    public int $registerCalls = 0;

    public int $bootCalls = 0;

    /** @var list<string> */
    private array $calls;

    /**
     * @param list<string> $calls
     */
    public function __construct(
        private readonly string $name = 'provider',
        array &$calls = []
    ) {
        $this->calls =& $calls;
    }

    public function register(): void
    {
        ++$this->registerCalls;
        $this->calls[] = 'register:' . $this->name;
    }

    public function boot(KernelInterface $kernel): void
    {
        ++$this->bootCalls;
        $this->calls[] = 'boot:' . $this->name;
    }
}

final class SecondaryRecordingServiceProvider extends RecordingServiceProvider
{
}
