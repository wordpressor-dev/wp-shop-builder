<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use WPShop\Core\Container\Container;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Container\Exception\ServiceAlreadyRegistered;
use WPShop\Core\Container\Exception\ServiceNotFound;

final class ContainerTest extends TestCase
{
    public function testContainerImplementsPsr11(): void
    {
        $container = new Container();

        self::assertInstanceOf(PsrContainerInterface::class, $container);
        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testServiceCanBeRegisteredAndRetrieved(): void
    {
        $container = new Container();
        $service = new TestService('catalog');

        $container->set(TestService::class, $service);

        self::assertSame($service, $container->get(TestService::class));
    }

    public function testContainerKnowsWhetherServiceExists(): void
    {
        $container = new Container();

        self::assertTrue($container->has(TestService::class));
        self::assertFalse($container->has('UnknownService'));

        $container->set(TestService::class, new TestService('catalog'));

        self::assertTrue($container->has(TestService::class));
    }

    public function testUnknownServiceThrowsPsrNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFound::class);
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Service "unknown-service" was not found in the container.');

        $container->get('unknown-service');
    }

    public function testServiceCannotBeRegisteredTwice(): void
    {
        $container = new Container();
        $container->set(TestService::class, new TestService('first'));

        $this->expectException(ServiceAlreadyRegistered::class);
        $this->expectExceptionMessage(sprintf('Service "%s" is already registered.', TestService::class));

        $container->set(TestService::class, new TestService('second'));
    }

    public function testFactoryIsResolvedLazily(): void
    {
        $container = new Container();
        $factoryExecutions = 0;

        $container->factory(
            TestService::class,
            static function () use (&$factoryExecutions): TestService {
                $factoryExecutions++;
                return new TestService('catalog');
            }
        );

        self::assertSame(0, $factoryExecutions);
        $service = $container->get(TestService::class);
        self::assertInstanceOf(TestService::class, $service);
        self::assertSame(1, $factoryExecutions);
    }

    public function testFactoryServiceIsShared(): void
    {
        $container = new Container();
        $container->factory(TestService::class, static fn (): TestService => new TestService('catalog'));

        self::assertSame(
            $container->get(TestService::class),
            $container->get(TestService::class)
        );
    }

    public function testFactoryReceivesContainer(): void
    {
        $container = new Container();
        $dependency = new TestDependency();

        $container->set(TestDependency::class, $dependency);
        $container->factory(
            TestServiceWithDependency::class,
            static fn (ContainerInterface $container): TestServiceWithDependency =>
                new TestServiceWithDependency($container->get(TestDependency::class))
        );

        $service = $container->get(TestServiceWithDependency::class);
        self::assertSame($dependency, $service->dependency);
    }

    public function testNullCanBeStoredAsAService(): void
    {
        $container = new Container();
        $container->set('nullable-service', null);

        self::assertTrue($container->has('nullable-service'));
        self::assertNull($container->get('nullable-service'));
    }
}

final readonly class TestService
{
    public function __construct(public string $name)
    {
    }
}

final class TestDependency
{
}

final readonly class TestServiceWithDependency
{
    public function __construct(public TestDependency $dependency)
    {
    }
}
