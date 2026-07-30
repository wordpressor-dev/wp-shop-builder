<?php

declare(strict_types=1);

namespace WPShop\Tests\Core\Config;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Config\ConfigRepository;

final class ConfigRepositoryTest extends TestCase
{
    public function testTopLevelValueCanBeRead(): void
    {
        $config = new ConfigRepository([
            'environment' => 'testing',
        ]);

        self::assertSame('testing', $config->get('environment'));
    }

    public function testNestedValueCanBeReadUsingDotNotation(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        self::assertSame('localhost', $config->get('database.host'));
        self::assertSame(3306, $config->get('database.port'));
    }

    public function testDefaultValueIsReturnedForMissingKey(): void
    {
        $config = new ConfigRepository();

        self::assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function testNullValueIsNotTreatedAsMissing(): void
    {
        $config = new ConfigRepository([
            'nullable' => null,
        ]);

        self::assertTrue($config->has('nullable'));
        self::assertNull($config->get('nullable', 'fallback'));
    }

    public function testHasReturnsFalseForMissingNestedKey(): void
    {
        $config = new ConfigRepository([
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        self::assertFalse($config->has('database.username'));
    }

    public function testAllReturnsCompleteConfiguration(): void
    {
        $items = [
            'app' => [
                'name' => 'WP Shop Builder',
            ],
        ];

        $config = new ConfigRepository($items);

        self::assertSame($items, $config->all());
    }

    public function testEmptyKeyReturnsCompleteConfiguration(): void
    {
        $items = [
            'app' => [
                'name' => 'WP Shop Builder',
            ],
        ];

        $config = new ConfigRepository($items);

        self::assertSame($items, $config->get(''));
    }

    public function testMergeReturnsNewImmutableRepository(): void
    {
        $original = new ConfigRepository([
            'app' => [
                'name' => 'WP Shop Builder',
                'debug' => false,
            ],
        ]);

        $merged = $original->merge([
            'app' => [
                'debug' => true,
            ],
        ]);

        self::assertNotSame($original, $merged);
        self::assertFalse($original->get('app.debug'));
        self::assertTrue($merged->get('app.debug'));
        self::assertSame(
            'WP Shop Builder',
            $merged->get('app.name')
        );
    }

    public function testNumericArraysAreReplacedByOverride(): void
    {
        $original = new ConfigRepository([
            'modules' => ['catalog', 'checkout'],
        ]);

        $merged = $original->merge([
            'modules' => ['catalog'],
        ]);

        self::assertSame(['catalog'], $merged->get('modules'));
    }
}
