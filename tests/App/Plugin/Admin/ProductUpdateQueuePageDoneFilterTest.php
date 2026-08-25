<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WPShop\App\Plugin\Admin\ProductUpdateQueuePage;

final class ProductUpdateQueuePageDoneFilterTest extends TestCase
{
    public function testDoneFilterBuildsRowsFromSeenProducts(): void
    {
        $page = new ProductUpdateQueuePage(
            static function (string $name, mixed ...$arguments): mixed {
                if ($name === 'get_post_field') {
                    return $arguments[2] === 5024
                        ? 'Eduma – Education WordPress Theme 5.9.5'
                        : '';
                }

                if ($name === 'get_post_meta') {
                    return match ($arguments[1]) {
                        'attr_version_value' => '5.9.5',
                        '_wp_shop_source_update_date' => '2026-08-22',
                        default => '',
                    };
                }

                return null;
            }
        );

        $doneRows = $this->invoke(
            $page,
            'doneRows',
            [[5024 => 'DONE', 9000 => 'UPDATE_AVAILABLE']]
        );

        self::assertCount(1, $doneRows);
        self::assertSame(5024, $doneRows[0]['productId']);
        self::assertSame('DONE', $doneRows[0]['status']);
        self::assertSame('5.9.5', $doneRows[0]['currentVersion']);

        $filtered = $this->invoke(
            $page,
            'rowsForFilter',
            [[], [], $doneRows, 'done']
        );

        self::assertSame($doneRows, $filtered);
        self::assertSame(
            'done',
            $this->invoke($page, 'normalizeFilter', ['done'])
        );
        self::assertSame(
            'DONE',
            $this->invoke($page, 'filterLabel', ['done'])
        );
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invoke(
        ProductUpdateQueuePage $page,
        string $method,
        array $arguments
    ): mixed {
        return (new ReflectionMethod($page, $method))->invokeArgs(
            $page,
            $arguments
        );
    }
}
