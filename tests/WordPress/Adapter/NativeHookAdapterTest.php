<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Adapter;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Adapter\NativeHookAdapter;
use WPShop\WordPress\Exception\WordPressFunctionUnavailable;

final class NativeHookAdapterTest extends TestCase
{
    public function testActionRegistrationFailsClearlyWithoutWordPress(): void
    {
        if (function_exists('add_action')) {
            self::markTestSkipped('WordPress is loaded in the test process.');
        }

        $this->expectException(WordPressFunctionUnavailable::class);
        $this->expectExceptionMessage('add_action');

        (new NativeHookAdapter())->addAction('init', static function (): void {
        });
    }

    public function testFilterRegistrationFailsClearlyWithoutWordPress(): void
    {
        if (function_exists('add_filter')) {
            self::markTestSkipped('WordPress is loaded in the test process.');
        }

        $this->expectException(WordPressFunctionUnavailable::class);
        $this->expectExceptionMessage('add_filter');

        (new NativeHookAdapter())->addFilter('the_content', static fn (string $value): string => $value);
    }
}
