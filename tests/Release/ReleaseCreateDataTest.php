<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\ReleaseCreateData;

final class ReleaseCreateDataTest extends TestCase
{
    public function testExposesCreationData(): void
    {
        $data = new ReleaseCreateData(
            7,
            '1.2.3',
            'validated',
            15,
            true,
            98.75
        );

        self::assertSame(
            7,
            $data->blueprintId()
        );

        self::assertSame(
            '1.2.3',
            $data->version()
        );

        self::assertSame(
            'validated',
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
            98.75,
            $data->validationScore()
        );
    }

    public function testUsesCreationDefaults(): void
    {
        $data = new ReleaseCreateData(
            7,
            '1.0.0'
        );

        self::assertSame(
            'draft',
            $data->status()
        );

        self::assertNull(
            $data->manifestId()
        );

        self::assertFalse(
            $data->published()
        );

        self::assertNull(
            $data->validationScore()
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

    public function testRejectsInvalidBlueprintIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release blueprintId must be a positive integer.'
        );

        $this->data(blueprintId: 0);
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

    public function testRejectsNonFiniteValidationScore(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->data(
            validationScore: INF
        );
    }

    private function data(
        int $blueprintId = 7,
        string $version = '1.2.3',
        string $status = 'draft',
        ?int $manifestId = null,
        bool $published = false,
        ?float $validationScore = null
    ): ReleaseCreateData {
        return new ReleaseCreateData(
            $blueprintId,
            $version,
            $status,
            $manifestId,
            $published,
            $validationScore
        );
    }
}
