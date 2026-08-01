<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\Release;

final class ReleaseTest extends TestCase
{
    public function testExposesReleaseData(): void
    {
        $createdAt = new DateTimeImmutable(
            '2026-08-01 12:00:00'
        );

        $release = new Release(
            42,
            7,
            '1.2.3',
            'published',
            15,
            true,
            98.75,
            $createdAt
        );

        self::assertSame(42, $release->id());
        self::assertSame(7, $release->blueprintId());
        self::assertSame('1.2.3', $release->version());
        self::assertSame('published', $release->status());
        self::assertSame(15, $release->manifestId());
        self::assertTrue($release->published());

        self::assertSame(
            98.75,
            $release->validationScore()
        );

        self::assertSame(
            $createdAt,
            $release->createdAt()
        );
    }

    public function testAllowsOptionalReleaseData(): void
    {
        $release = $this->release(
            manifestId: null,
            validationScore: null
        );

        self::assertNull($release->manifestId());

        self::assertNull(
            $release->validationScore()
        );

        self::assertFalse($release->published());
    }

    public function testAcceptsValidationScoreBoundaries(): void
    {
        self::assertSame(
            0.0,
            $this->release(
                validationScore: 0.0
            )->validationScore()
        );

        self::assertSame(
            100.0,
            $this->release(
                validationScore: 100.0
            )->validationScore()
        );
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release id must be a positive integer.'
        );

        $this->release(id: 0);
    }

    public function testRejectsInvalidBlueprintIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release blueprintId must be a positive integer.'
        );

        $this->release(blueprintId: 0);
    }

    public function testRejectsEmptyVersion(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release version must contain between 1 and 64 characters.'
        );

        $this->release(version: '   ');
    }

    public function testRejectsVersionAboveMaximumLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->release(
            version: str_repeat('a', 65)
        );
    }

    public function testRejectsEmptyStatus(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release status must contain between 1 and 64 characters.'
        );

        $this->release(status: '');
    }

    public function testRejectsStatusAboveMaximumLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->release(
            status: str_repeat('a', 65)
        );
    }

    public function testRejectsInvalidManifestIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release manifestId must be a positive integer.'
        );

        $this->release(manifestId: 0);
    }

    public function testRejectsNegativeValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release validationScore must be between 0 and 100.'
        );

        $this->release(
            validationScore: -0.01
        );
    }

    public function testRejectsValidationScoreAboveMaximum(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->release(
            validationScore: 100.01
        );
    }

    public function testRejectsNonFiniteValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->release(
            validationScore: INF
        );
    }

    private function release(
        int $id = 42,
        int $blueprintId = 7,
        string $version = '1.2.3',
        string $status = 'draft',
        ?int $manifestId = null,
        bool $published = false,
        ?float $validationScore = null
    ): Release {
        return new Release(
            $id,
            $blueprintId,
            $version,
            $status,
            $manifestId,
            $published,
            $validationScore,
            new DateTimeImmutable(
                '2026-08-01 12:00:00'
            )
        );
    }
}
