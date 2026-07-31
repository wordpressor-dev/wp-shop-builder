<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Adapter;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Adapter\TestingHookAdapter;

final class TestingHookAdapterTest extends TestCase
{
    public function testRegistersAndRunsActionsByPriority(): void
    {
        $adapter = new TestingHookAdapter();
        $calls = [];

        $adapter->addAction('init', static function () use (&$calls): void {
            $calls[] = 'late';
        }, 20);

        $adapter->addAction('init', static function () use (&$calls): void {
            $calls[] = 'early';
        }, 5);

        $adapter->doAction('init');

        self::assertTrue($adapter->hasAction('init'));
        self::assertSame(['early', 'late'], $calls);
    }

    public function testLimitsActionArgumentsToAcceptedCount(): void
    {
        $adapter = new TestingHookAdapter();
        $received = [];

        $adapter->addAction(
            'save_post',
            static function (int $postId) use (&$received): void {
                $received[] = $postId;
            },
            10,
            1
        );

        $adapter->doAction('save_post', 42, 'ignored');

        self::assertSame([42], $received);
    }

    public function testAppliesFiltersByPriority(): void
    {
        $adapter = new TestingHookAdapter();

        $adapter->addFilter(
            'title',
            static fn (string $value): string => $value . ' second',
            20
        );

        $adapter->addFilter(
            'title',
            static fn (string $value): string => $value . ' first',
            5
        );

        self::assertTrue($adapter->hasFilter('title'));
        self::assertSame(
            'Product first second',
            $adapter->applyFilters('title', 'Product')
        );
    }

    public function testReturnsOriginalValueWhenFilterIsMissing(): void
    {
        $adapter = new TestingHookAdapter();

        self::assertSame('unchanged', $adapter->applyFilters('missing', 'unchanged'));
        self::assertFalse($adapter->hasFilter('missing'));
    }
}
