<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\ReleaseQuery;

final class ReleaseQueryTest extends TestCase
{
    public function testUsesDefaultCollectionCriteria(): void
    {
        $query = new ReleaseQuery();

        self::assertNull($query->blueprintId());
        self::assertNull($query->status());
        self::assertNull($query->published());

        self::assertSame(
            ReleaseQuery::SORT_ID,
            $query->sortBy()
        );

        self::assertSame(
            ReleaseQuery::DIRECTION_DESCENDING,
            $query->sortDirection()
        );

        self::assertSame(50, $query->limit());
        self::assertSame(0, $query->offset());
    }

    public function testExposesCollectionCriteria(): void
    {
        $query = new ReleaseQuery(
            7,
            'published',
            true,
            ReleaseQuery::SORT_CREATED_AT,
            ReleaseQuery::DIRECTION_ASCENDING,
            25,
            50
        );

        self::assertSame(7, $query->blueprintId());

        self::assertSame(
            'published',
            $query->status()
        );

        self::assertTrue($query->published());

        self::assertSame(
            ReleaseQuery::SORT_CREATED_AT,
            $query->sortBy()
        );

        self::assertSame(
            ReleaseQuery::DIRECTION_ASCENDING,
            $query->sortDirection()
        );

        self::assertSame(25, $query->limit());
        self::assertSame(50, $query->offset());
    }

    public function testRejectsInvalidBlueprintIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query blueprint identifier must be positive.'
        );

        new ReleaseQuery(
            blueprintId: 0
        );
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query status must contain between 1 and 64 characters.'
        );

        new ReleaseQuery(
            status: ' '
        );
    }

    public function testRejectsInvalidSortField(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query sort field is invalid.'
        );

        new ReleaseQuery(
            sortBy: 'manifest'
        );
    }

    public function testRejectsInvalidSortDirection(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query sort direction is invalid.'
        );

        new ReleaseQuery(
            sortDirection: 'random'
        );
    }

    public function testRejectsLimitOutsideAllowedRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query limit must be between 1 and 100.'
        );

        new ReleaseQuery(
            limit: 101
        );
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release query offset cannot be negative.'
        );

        new ReleaseQuery(
            offset: -1
        );
    }
}
