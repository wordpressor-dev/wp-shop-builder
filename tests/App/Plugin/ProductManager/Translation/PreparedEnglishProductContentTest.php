<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Translation;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Translation\PreparedEnglishProductContent;

final class PreparedEnglishProductContentTest extends TestCase
{
    public function testUsesPreparedEnglishOnEnglishLocale(): void
    {
        $meta = [
            '_wp_shop_en_short_description' => '<p>English short.</p>',
            '_wp_shop_en_long_description' => '<h2>English long</h2>',
        ];
        $call = static function (
            string $name,
            mixed ...$arguments
        ) use ($meta): mixed {
            return match ($name) {
                'is_admin' => false,
                'get_locale' => 'en_US',
                'function_exists' => true,
                'determine_locale' => 'en_US',
                'get_queried_object_id' => 4561,
                'get_post_type' => 'product',
                'get_the_ID' => 4561,
                'get_post_meta' => $meta[(string) $arguments[1]] ?? '',
                default => null,
            };
        };
        $content = new PreparedEnglishProductContent($call(...));

        self::assertSame(
            '<p>English short.</p>',
            $content->filterShortDescription('<p>Русский short.</p>')
        );
        self::assertSame(
            '<h2>English long</h2>',
            $content->filterLongDescription('<h2>Русский long</h2>')
        );
    }

    public function testKeepsRussianContentOnRussianLocale(): void
    {
        $call = static function (string $name): mixed {
            return match ($name) {
                'is_admin' => false,
                'get_locale' => 'ru_RU',
                'function_exists' => true,
                'determine_locale' => 'ru_RU',
                'get_queried_object_id' => 4561,
                'get_post_type' => 'product',
                'get_the_ID' => 4561,
                default => null,
            };
        };
        $content = new PreparedEnglishProductContent($call(...));

        self::assertSame(
            '<p>Русский текст.</p>',
            $content->filterShortDescription('<p>Русский текст.</p>')
        );
    }
}
