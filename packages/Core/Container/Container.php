<?php

declare(strict_types=1);

namespace WPShop\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use WPShop\Core\Container\Exception\AutowireException;
use WPShop\Core\Container\Exception\ServiceAlreadyRegistered;
use WPShop\Core\Container\Exception\ServiceNotFound;

final class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $definitions = [];

    /**
     * @var array<string, mixed>
     */
    private array $resolved = [];

    public function set(string $id, mixed $service): void
    {
        $this->assertNotRegistered($id);

        $this->definitions[$id] = $service;
    }

    public function factory(string $id, Closure $resolver): void
    {
        $this->assertNotRegistered($id);

        $this->definitions[$id] = new Factory($resolver);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (array_key_exists($id, $this->definitions)) {
            return $this->resolveDefinition($id);
        }

        if (class_exists($id) || interface_exists($id)) {
            return $this->autowire($id);
        }

        throw ServiceNotFound::forId($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions)
            || array_key_exists($id, $this->resolved)
            || class_exists($id);
    }

    public function autowire(string $id): object
    {
        if (array_key_exists($id, $this->resolved)) {
            $service = $this->resolved[$id];

            if (!is_object($service)) {
                throw AutowireException::classIsNotInstantiable($id);
            }

            return $service;
        }

        if (!class_exists($id) && !interface_exists($id)) {
            throw AutowireException::classDoesNotExist($id);
        }

        try {
            $reflection = new ReflectionClass($id);
        } catch (ReflectionException $exception) {
            throw AutowireException::reflectionFailed($id, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw AutowireException::classIsNotInstantiable($id);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = $reflection->newInstance();
            $this->resolved[$id] = $instance;

            return $instance;
        }

        $arguments = array_map(
            fn (ReflectionParameter $parameter): mixed =>
                $this->resolveParameter($id, $parameter),
            $constructor->getParameters()
        );

        $instance = $reflection->newInstanceArgs($arguments);
        $this->resolved[$id] = $instance;

        return $instance;
    }

    private function resolveDefinition(string $id): mixed
    {
        $definition = $this->definitions[$id];

        if ($definition instanceof Factory) {
            $definition = $definition->resolve($this);
        }

        $this->resolved[$id] = $definition;

        return $definition;
    }

    private function resolveParameter(
        string $className,
        ReflectionParameter $parameter
    ): mixed {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw AutowireException::parameterCannotBeResolved(
            $className,
            $parameter->getName()
        );
    }

    private function assertNotRegistered(string $id): void
    {
        if (
            array_key_exists($id, $this->definitions)
            || array_key_exists($id, $this->resolved)
        ) {
            throw ServiceAlreadyRegistered::forId($id);
        }
    }
}
