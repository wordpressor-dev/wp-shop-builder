<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\ManifestQuery;

final class ManifestQueryTest extends TestCase
{
    public function testUsesDefaultCollectionCriteria(): void
    {
        $query = new ManifestQuery();

        self::assertNull($query->releaseId());
        self::assertNull($query->manifestHash());

        self::assertSame(
            ManifestQuery::SORT_ID,
            $query->sortBy()
        );

        self::assertSame(
            ManifestQuery::DIRECTION_DESCENDING,
            $query->sortDirection()
        );

        self::assertSame(50, $query->limit());
        self::assertSame(0, $query->offset());
    }

    public function testExposesCollectionCriteria(): void
    {
        $manifestHash = str_repeat('A', 64);

        $query = new ManifestQuery(
            7,
            $manifestHash,
            ManifestQuery::SORT_CREATED_AT,
            ManifestQuery::DIRECTION_ASCENDING,
            25,
            50
        );

        self::assertSame(7, $query->releaseId());

        self::assertSame(
            $manifestHash,
            $query->manifestHash()
        );

        self::assertSame(
            ManifestQuery::SORT_CREATED_AT,
            $query->sortBy()
        );

        self::assertSame(
            ManifestQuery::DIRECTION_ASCENDING,
            $query->sortDirection()
        );

        self::assertSame(25, $query->limit());
        self::assertSame(50, $query->offset());
    }

    public function testRejectsInvalidReleaseIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query release identifier must be positive.'
        );

        new ManifestQuery(
            releaseId: 0
        );
    }

    public function testRejectsInvalidManifestHashLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query manifest hash must contain '
            . '64 hexadecimal characters.'
        );

        new ManifestQuery(
            manifestHash: str_repeat('a', 63)
        );
    }

    public function testRejectsNonHexadecimalManifestHash(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new ManifestQuery(
            manifestHash: str_repeat('g', 64)
        );
    }

    public function testRejectsInvalidSortField(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query sort field is invalid.'
        );

        new ManifestQuery(
            sortBy: 'version'
        );
    }

    public function testRejectsInvalidSortDirection(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query sort direction is invalid.'
        );

        new ManifestQuery(
            sortDirection: 'random'
        );
    }

    #[DataProvider('invalidLimitProvider')]
    public function testRejectsLimitOutsideAllowedRange(
        int $limit
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query limit must be between 1 and 100.'
        );

        new ManifestQuery(
            limit: $limit
        );
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest query offset cannot be negative.'
        );

        new ManifestQuery(
            offset: -1
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLimitProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'above maximum' => [101];
    }
}
