<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\Release;
use WPShop\Release\ReleasePage;

final class ReleasePageTest extends TestCase
{
    public function testExposesPageDataAndCalculatesPages(): void
    {
        $items = [
            $this->release(42, '1.2.3'),
            $this->release(43, '1.2.4'),
        ];

        $page = new ReleasePage(
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
        $page = new ReleasePage(
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
            'Release page total cannot be negative.'
        );

        new ReleasePage(
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
            'Release page limit must be positive.'
        );

        new ReleasePage(
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
            'Release page offset cannot be negative.'
        );

        new ReleasePage(
            [],
            0,
            50,
            -1
        );
    }

    public function testRejectsItemsAbovePageLimit(): void
    {
        $items = [
            $this->release(42, '1.2.3'),
            $this->release(43, '1.2.4'),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release page items cannot exceed the page limit.'
        );

        new ReleasePage(
            $items,
            2,
            1,
            0
        );
    }

    public function testRejectsItemsAboveTotal(): void
    {
        $items = [
            $this->release(42, '1.2.3'),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release page items cannot exceed the total.'
        );

        new ReleasePage(
            $items,
            0,
            50,
            0
        );
    }

    private function release(
        int $id,
        string $version
    ): Release {
        return new Release(
            $id,
            7,
            $version,
            'draft',
            null,
            false,
            null,
            new DateTimeImmutable(
                '2026-08-01 12:00:00'
            )
        );
    }
}
