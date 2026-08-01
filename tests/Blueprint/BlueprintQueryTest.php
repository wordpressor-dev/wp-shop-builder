<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\BlueprintQuery;

final class BlueprintQueryTest extends TestCase
{
    public function testUsesDefaultCollectionCriteria(): void
    {
        $query = new BlueprintQuery();

        self::assertNull($query->type());
        self::assertNull($query->state());
        self::assertNull($query->workflow());
        self::assertFalse($query->includingDeleted());

        self::assertSame(
            BlueprintQuery::SORT_ID,
            $query->sortBy()
        );

        self::assertSame(
            BlueprintQuery::DIRECTION_DESCENDING,
            $query->sortDirection()
        );

        self::assertSame(50, $query->limit());
        self::assertSame(0, $query->offset());
    }

    public function testExposesCollectionCriteria(): void
    {
        $query = new BlueprintQuery(
            'plugin',
            'published',
            'reviewed',
            true,
            BlueprintQuery::SORT_UPDATED_AT,
            BlueprintQuery::DIRECTION_ASCENDING,
            25,
            50
        );

        self::assertSame('plugin', $query->type());
        self::assertSame('published', $query->state());
        self::assertSame('reviewed', $query->workflow());
        self::assertTrue($query->includingDeleted());

        self::assertSame(
            BlueprintQuery::SORT_UPDATED_AT,
            $query->sortBy()
        );

        self::assertSame(
            BlueprintQuery::DIRECTION_ASCENDING,
            $query->sortDirection()
        );

        self::assertSame(25, $query->limit());
        self::assertSame(50, $query->offset());
    }

    public function testRejectsEmptyFilter(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint query state must contain between 1 and 64 characters.'
        );

        new BlueprintQuery(
            state: ' '
        );
    }

    public function testRejectsInvalidSortField(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint query sort field is invalid.'
        );

        new BlueprintQuery(
            sortBy: 'deletedAt'
        );
    }

    public function testRejectsInvalidSortDirection(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint query sort direction is invalid.'
        );

        new BlueprintQuery(
            sortDirection: 'random'
        );
    }

    public function testRejectsLimitOutsideAllowedRange(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint query limit must be between 1 and 100.'
        );

        new BlueprintQuery(
            limit: 101
        );
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint query offset cannot be negative.'
        );

        new BlueprintQuery(
            offset: -1
        );
    }
}
