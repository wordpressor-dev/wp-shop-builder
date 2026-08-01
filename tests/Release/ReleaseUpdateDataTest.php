<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\ReleaseUpdateData;

final class ReleaseUpdateDataTest extends TestCase
{
    public function testExposesUpdateData(): void
    {
        $data = new ReleaseUpdateData(
            '2.0.0',
            'published',
            15,
            true,
            99.5
        );

        self::assertSame(
            '2.0.0',
            $data->version()
        );

        self::assertSame(
            'published',
            $data->status()
        );

        self::assertSame(
            15,
            $data->manifestId()
        );

        self::assertTrue(
            $data->published()
        );

        self::assertSame(
            99.5,
            $data->validationScore()
        );
    }

    public function testAllowsNullableUpdateData(): void
    {
        $data = $this->data(
            manifestId: null,
            validationScore: null
        );

        self::assertNull(
            $data->manifestId()
        );

        self::assertNull(
            $data->validationScore()
        );

        self::assertFalse(
            $data->published()
        );
    }

    public function testAcceptsValidationScoreBoundaries(): void
    {
        self::assertSame(
            0.0,
            $this->data(
                validationScore: 0.0
            )->validationScore()
        );

        self::assertSame(
            100.0,
            $this->data(
                validationScore: 100.0
            )->validationScore()
        );
    }

    public function testRejectsEmptyVersion(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release version must contain between 1 and 64 characters.'
        );

        $this->data(version: '   ');
    }

    public function testRejectsVersionAboveMaximumLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
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

        $this->data(status: '');
    }

    public function testRejectsStatusAboveMaximumLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
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

        $this->data(manifestId: 0);
    }

    public function testRejectsNegativeValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release validationScore must be between 0 and 100.'
        );

        $this->data(
            validationScore: -0.01
        );
    }

    public function testRejectsValidationScoreAboveMaximum(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
            validationScore: 100.01
        );
    }

    public function testRejectsInfiniteValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
            validationScore: INF
        );
    }

    public function testRejectsNanValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
            validationScore: NAN
        );
    }

    private function data(
        string $version = '1.2.3',
        string $status = 'draft',
        ?int $manifestId = null,
        bool $published = false,
        ?float $validationScore = null
    ): ReleaseUpdateData {
        return new ReleaseUpdateData(
            $version,
            $status,
            $manifestId,
            $published,
            $validationScore
        );
    }
}
