<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Hook;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\WordPress\Adapter\TestingHookAdapter;
use WPShop\WordPress\Hook\HookRegistrar;
use WPShop\WordPress\Hook\HookResolver;

final class HookRegistrarTest extends TestCase
{
    public function testRegistersAndExecutesActionCallback(): void
    {
        $adapter = new TestingHookAdapter();
        $registrar = new HookRegistrar(
            $adapter,
            new HookResolver(new Container())
        );
        $received = null;

        $registrar->action(
            'wp_shop_ready',
            static function (string $value) use (&$received): void {
                $received = $value;
            },
            20,
            1
        );

        $adapter->doAction('wp_shop_ready', 'ready', 'ignored');

        self::assertSame('ready', $received);
        self::assertSame(20, $adapter->actions('wp_shop_ready')[0]['priority']);
        self::assertSame(1, $adapter->actions('wp_shop_ready')[0]['accepted_args']);
    }

    public function testRegistersAndExecutesFilterService(): void
    {
        $container = new Container();
        $container->set(RegistrarFilterListener::class, new RegistrarFilterListener());
        $adapter = new TestingHookAdapter();
        $registrar = new HookRegistrar($adapter, new HookResolver($container));

        $registrar->filter('wp_shop_title', RegistrarFilterListener::class, 5, 2);

        self::assertSame(
            'Builder 2',
            $adapter->applyFilters('wp_shop_title', 'Builder', 2, 'ignored')
        );
        self::assertSame(5, $adapter->filters('wp_shop_title')[0]['priority']);
        self::assertSame(2, $adapter->filters('wp_shop_title')[0]['accepted_args']);
    }
}

final class RegistrarFilterListener
{
    public function __invoke(string $title, int $version): string
    {
        return sprintf('%s %d', $title, $version);
    }
}
