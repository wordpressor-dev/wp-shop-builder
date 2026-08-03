<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Release\ReleasePublicationData;

final class ReleasePublicationDataTest extends TestCase
{
    public function testExposesPublicationData(): void
    {
        $data = new ReleasePublicationData(
            42,
            '{"name":"example-plugin"}',
            98.75
        );

        self::assertSame(42, $data->releaseId());

        self::assertSame(
            '{"name":"example-plugin"}',
            $data->manifestJson()
        );

        self::assertSame(
            98.75,
            $data->validationScore()
        );
    }

    public function testAcceptsNullValidationScore(): void
    {
        $data = new ReleasePublicationData(
            42,
            '{"name":"example-plugin"}',
            null
        );

        self::assertNull($data->validationScore());
    }

    #[DataProvider('invalidReleaseIdentifiers')]
    public function testRejectsInvalidReleaseIdentifier(
        int $releaseId
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release publication releaseId must be a positive integer.'
        );

        new ReleasePublicationData(
            $releaseId,
            '{"name":"example-plugin"}',
            null
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidReleaseIdentifiers(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('emptyManifestJsonValues')]
    public function testRejectsEmptyManifestJson(
        string $manifestJson
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release publication manifestJson cannot be empty.'
        );

        new ReleasePublicationData(
            42,
            $manifestJson,
            null
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyManifestJsonValues(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [" \t\n "];
    }

    public function testRejectsInvalidManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release publication manifestJson must contain valid JSON.'
        );

        new ReleasePublicationData(
            42,
            '{"name":}',
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
            'Release publication validationScore must be between 0 and 100.'
        );

        new ReleasePublicationData(
            42,
            '{"name":"example-plugin"}',
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
        yield 'positive infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'not a number' => [NAN];
    }
}
