<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;

final class BlueprintTest extends TestCase
{
    public function testExposesPersistedBlueprintData(): void
    {
        $createdAt = new DateTimeImmutable(
            '2026-07-31 10:00:00'
        );

        $updatedAt = new DateTimeImmutable(
            '2026-07-31 11:00:00'
        );

        $blueprint = new Blueprint(
            42,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-plugin',
            'plugin',
            7,
            9,
            11,
            'draft',
            'default',
            $createdAt,
            $updatedAt,
            null
        );

        self::assertSame(42, $blueprint->id());

        self::assertSame(
            '123e4567-e89b-12d3-a456-426614174000',
            $blueprint->uuid()
        );

        self::assertSame(
            'example-plugin',
            $blueprint->slug()
        );

        self::assertSame(
            'plugin',
            $blueprint->type()
        );

        self::assertSame(7, $blueprint->providerId());
        self::assertSame(9, $blueprint->developerId());

        self::assertSame(
            11,
            $blueprint->currentReleaseId()
        );

        self::assertSame(
            'draft',
            $blueprint->state()
        );

        self::assertSame(
            'default',
            $blueprint->workflow()
        );

        self::assertSame(
            $createdAt,
            $blueprint->createdAt()
        );

        self::assertSame(
            $updatedAt,
            $blueprint->updatedAt()
        );

        self::assertNull($blueprint->deletedAt());
    }

    public function testRejectsInvalidUuid(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint UUID is invalid.'
        );

        new Blueprint(
            1,
            'invalid-uuid',
            'example',
            'plugin',
            null,
            null,
            null,
            'draft',
            'default',
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            null
        );
    }
}
