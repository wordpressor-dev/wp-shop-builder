<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Container;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Core\Container\Exception\AutowireException;

final class AutowiringTest extends TestCase
{
    public function testClassWithoutConstructorCanBeAutowired(): void
    {
        $container = new Container();

        $service = $container->get(PlainService::class);

        self::assertInstanceOf(PlainService::class, $service);
    }

    public function testConstructorDependenciesAreAutowiredRecursively(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithDependency::class);

        self::assertInstanceOf(ServiceWithDependency::class, $service);
        self::assertInstanceOf(PlainService::class, $service->dependency);
    }

    public function testAutowiredServicesAreShared(): void
    {
        $container = new Container();

        $first = $container->get(ServiceWithDependency::class);
        $second = $container->get(ServiceWithDependency::class);

        self::assertSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);
    }

    public function testRegisteredDependencyOverridesAutowiring(): void
    {
        $container = new Container();
        $dependency = new PlainService();

        $container->set(PlainService::class, $dependency);

        $service = $container->get(ServiceWithDependency::class);

        self::assertSame($dependency, $service->dependency);
    }

    public function testDefaultScalarConstructorValueIsUsed(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithDefaultValue::class);

        self::assertSame('catalog', $service->name);
    }

    public function testNullableScalarConstructorValueResolvesToNull(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithNullableValue::class);

        self::assertNull($service->name);
    }

    public function testRequiredScalarParameterCannotBeAutowired(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Parameter "$name" of class "%s" cannot be resolved automatically.',
                ServiceWithRequiredScalar::class
            )
        );

        $container->get(ServiceWithRequiredScalar::class);
    }

    public function testInterfaceCannotBeAutowiredDirectly(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Class "%s" is not instantiable and cannot be autowired.',
                TestContract::class
            )
        );

        $container->autowire(TestContract::class);
    }

    public function testInterfaceCanBeResolvedWhenImplementationIsRegistered(): void
    {
        $container = new Container();
        $implementation = new TestImplementation();

        $container->set(TestContract::class, $implementation);

        self::assertSame(
            $implementation,
            $container->get(TestContract::class)
        );
    }
}

final class PlainService
{
}

final readonly class ServiceWithDependency
{
    public function __construct(
        public PlainService $dependency
    ) {
    }
}

final readonly class ServiceWithDefaultValue
{
    public function __construct(
        public string $name = 'catalog'
    ) {
    }
}

final readonly class ServiceWithNullableValue
{
    public function __construct(
        public ?string $name
    ) {
    }
}

final readonly class ServiceWithRequiredScalar
{
    public function __construct(
        public string $name
    ) {
    }
}

interface TestContract
{
}

final class TestImplementation implements TestContract
{
}
