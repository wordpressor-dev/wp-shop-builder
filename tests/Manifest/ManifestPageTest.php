<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestPage;

final class ManifestPageTest extends TestCase
{
    public function testExposesPageDataAndCalculatesPages(): void
    {
        $items = [
            $this->manifest(42, 7),
            $this->manifest(43, 8),
        ];

        $page = new ManifestPage(
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
        $page = new ManifestPage(
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
            'Manifest page total cannot be negative.'
        );

        new ManifestPage(
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
            'Manifest page limit must be positive.'
        );

        new ManifestPage(
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
            'Manifest page offset cannot be negative.'
        );

        new ManifestPage(
            [],
            0,
            50,
            -1
        );
    }

    public function testRejectsItemsAbovePageLimit(): void
    {
        $items = [
            $this->manifest(42, 7),
            $this->manifest(43, 8),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest page items cannot exceed the page limit.'
        );

        new ManifestPage(
            $items,
            2,
            1,
            0
        );
    }

    public function testRejectsItemsAboveTotal(): void
    {
        $items = [
            $this->manifest(42, 7),
        ];

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest page items cannot exceed the total.'
        );

        new ManifestPage(
            $items,
            0,
            50,
            0
        );
    }

    private function manifest(
        int $id,
        int $releaseId
    ): Manifest {
        $manifestJson = sprintf(
            '{"releaseId":%d}',
            $releaseId
        );

        return new Manifest(
            $id,
            $releaseId,
            $manifestJson,
            hash(
                'sha256',
                $manifestJson
            ),
            new DateTimeImmutable(
                '2026-08-02 13:00:00'
            )
        );
    }
}
