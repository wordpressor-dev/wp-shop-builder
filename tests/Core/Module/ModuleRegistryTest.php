<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Module;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Contracts\ModuleInterface;
use WPShop\Core\Exception\ModuleAlreadyRegistered;
use WPShop\Core\Exception\ModuleNotFound;
use WPShop\Core\Module\ModuleRegistry;

final class ModuleRegistryTest extends TestCase
{
    public function testModuleCanBeRegistered(): void
    {
        $registry = new ModuleRegistry();
        $module = new TestModule('catalog', 'Catalog');

        $registry->register($module);

        self::assertTrue($registry->has('catalog'));
        self::assertSame($module, $registry->get('catalog'));
    }

    public function testRegisteredModulesCanBeRetrieved(): void
    {
        $registry = new ModuleRegistry();

        $catalog = new TestModule('catalog', 'Catalog');
        $checkout = new TestModule('checkout', 'Checkout');

        $registry->register($catalog);
        $registry->register($checkout);

        self::assertSame(
            [
                $catalog,
                $checkout,
            ],
            $registry->all()
        );
    }

    public function testDuplicateModuleCannotBeRegistered(): void
    {
        $registry = new ModuleRegistry();

        $registry->register(new TestModule('catalog', 'Catalog'));

        $this->expectException(ModuleAlreadyRegistered::class);

        $registry->register(new TestModule('catalog', 'Another Catalog'));
    }

    public function testUnknownModuleThrowsException(): void
    {
        $registry = new ModuleRegistry();

        $this->expectException(ModuleNotFound::class);

        $registry->get('missing-module');
    }

    public function testAllModulesCanBeBooted(): void
    {
        $registry = new ModuleRegistry();

        $catalog = new TestModule('catalog', 'Catalog');
        $checkout = new TestModule('checkout', 'Checkout');

        $registry->register($catalog);
        $registry->register($checkout);

        $registry->bootAll();

        self::assertTrue($catalog->wasBooted());
        self::assertTrue($checkout->wasBooted());
    }
}

final class TestModule implements ModuleInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly string $id,
        private readonly string $name
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function boot(): void
    {
        $this->booted = true;
    }

    public function wasBooted(): bool
    {
        return $this->booted;
    }
}
