<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintPage;

final class BlueprintPageTest extends TestCase
{
    public function testExposesPageDataAndCalculatesPages(): void
    {
        $items = [
            $this->blueprint(
                42,
                '123e4567-e89b-12d3-a456-426614174000'
            ),
            $this->blueprint(
                43,
                '123e4567-e89b-12d3-a456-426614174001'
            ),
        ];

        $page = new BlueprintPage(
            $items,
            101,
            25,
            50
        );

        self::assertSame($items, $page->items());
        self::assertSame(101, $page->total());
        self::assertSame(25, $page->limit());
        self::assertSame(50, $page->offset());
        self::assertSame(5, $page->totalPages());
    }

    public function testReturnsZeroPagesForEmptyResult(): void
    {
        $page = new BlueprintPage(
            [],
            0,
            50,
            0
        );

        self::assertSame([], $page->items());
        self::assertSame(0, $page->total());
        self::assertSame(0, $page->totalPages());
    }

    public function testRejectsNegativeTotal(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint page total cannot be negative.'
        );

        new BlueprintPage(
            [],
            -1,
            50,
            0
        );
    }

    public function testRejectsInvalidLimit(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint page limit must be positive.'
        );

        new BlueprintPage(
            [],
            0,
            0,
            0
        );
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint page offset cannot be negative.'
        );

        new BlueprintPage(
            [],
            0,
            50,
            -1
        );
    }

    public function testRejectsItemsAbovePageLimit(): void
    {
        $items = [
            $this->blueprint(
                42,
                '123e4567-e89b-12d3-a456-426614174000'
            ),
            $this->blueprint(
                43,
                '123e4567-e89b-12d3-a456-426614174001'
            ),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint page items cannot exceed the page limit.'
        );

        new BlueprintPage(
            $items,
            2,
            1,
            0
        );
    }

    public function testRejectsItemsAboveTotal(): void
    {
        $items = [
            $this->blueprint(
                42,
                '123e4567-e89b-12d3-a456-426614174000'
            ),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint page items cannot exceed the total.'
        );

        new BlueprintPage(
            $items,
            0,
            50,
            0
        );
    }

    private function blueprint(
        int $id,
        string $uuid
    ): Blueprint {
        $date = new DateTimeImmutable(
            '2026-08-01 10:00:00'
        );

        return new Blueprint(
            $id,
            $uuid,
            sprintf('plugin-%d', $id),
            'plugin',
            null,
            null,
            null,
            'draft',
            'default',
            $date,
            $date,
            null
        );
    }
}
