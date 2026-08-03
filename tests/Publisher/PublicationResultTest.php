<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PublicationResult;

final class PublicationResultTest extends TestCase
{
    public function testExposesPublicationResult(): void
    {
        $result = new PublicationResult(
            '{"name":"example"}',
            98.75
        );

        self::assertSame(
            '{"name":"example"}',
            $result->manifestJson()
        );

        self::assertSame(
            98.75,
            $result->validationScore()
        );
    }

    public function testAcceptsNullValidationScore(): void
    {
        $result = new PublicationResult(
            '{"name":"example"}',
            null
        );

        self::assertNull($result->validationScore());
    }

    public function testAcceptsValidationScoreBoundaries(): void
    {
        self::assertSame(
            0.0,
            (
                new PublicationResult(
                    '{}',
                    0.0
                )
            )->validationScore()
        );

        self::assertSame(
            100.0,
            (
                new PublicationResult(
                    '{}',
                    100.0
                )
            )->validationScore()
        );
    }

    public function testRejectsEmptyManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication result manifestJson cannot be empty.'
        );

        new PublicationResult(
            '   ',
            null
        );
    }

    public function testRejectsInvalidManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication result manifestJson must contain valid JSON.'
        );

        new PublicationResult(
            '{"invalid":',
            null
        );
    }

    #[DataProvider('invalidValidationScores')]
    public function testRejectsInvalidValidationScore(
        float $validationScore
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication result validationScore must be between 0 and 100.'
        );

        new PublicationResult(
            '{}',
            $validationScore
        );
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidValidationScores(): iterable
    {
        yield 'negative' => [-0.01];
        yield 'above maximum' => [100.01];
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }
}
