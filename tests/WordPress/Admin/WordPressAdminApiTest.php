<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\WordPress\Admin\WordPressAdminApi;
use WPShop\WordPress\Exception\WordPressFunctionUnavailable;

final class WordPressAdminApiTest extends TestCase
{
    public function testRejectsRegistrationWhenWordPressIsNotLoaded(): void
    {
        if (function_exists('add_menu_page')) {
            self::markTestSkipped('WordPress is loaded in the test environment.');
        }

        $this->expectException(WordPressFunctionUnavailable::class);
        $this->expectExceptionMessage('add_menu_page');

        (new WordPressAdminApi())->addMenuPage(
            'Builder',
            'Builder',
            'manage_options',
            'builder',
            static function (): void {
            }
        );
    }
}
