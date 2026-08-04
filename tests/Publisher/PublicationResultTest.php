<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\Exception\InvalidArtifactManifest;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\PublicationResult;

final class PublicationResultTest extends TestCase
{
    public function testExposesPublicationResult(): void
    {
        $artifact = $this->artifact();

        $result = new PublicationResult(
            '{"name":"example"}',
            98.75,
            $artifact
        );

        self::assertSame(
            '{"name":"example"}',
            $result->manifestJson()
        );

        self::assertSame(
            98.75,
            $result->validationScore()
        );

        self::assertSame(
            $artifact,
            $result->artifact()
        );
    }

    public function testAcceptsNullValidationScore(): void
    {
        $result = new PublicationResult(
            '{"name":"example"}',
            null,
            $this->artifact()
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
                    0.0,
                    $this->artifact()
                )
            )->validationScore()
        );

        self::assertSame(
            100.0,
            (
                new PublicationResult(
                    '{}',
                    100.0,
                    $this->artifact()
                )
            )->validationScore()
        );
    }

    #[DataProvider('invalidManifests')]
    public function testRejectsInvalidManifest(
        string $manifestJson,
        string $message
    ): void {
        $this->expectException(
            InvalidArtifactManifest::class
        );

        $this->expectExceptionMessage($message);

        new PublicationResult(
            $manifestJson,
            null,
            $this->artifact()
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
            $validationScore,
            $this->artifact()
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'empty' => [
            '   ',
            'Publication manifest JSON cannot be empty.',
        ];

        yield 'invalid JSON' => [
            '{"invalid":',
            'Publication manifest must contain valid JSON.',
        ];

        yield 'array' => [
            '[]',
            'Publication manifest JSON must contain an object.',
        ];

        yield 'scalar' => [
            '1',
            'Publication manifest JSON must contain an object.',
        ];

        yield 'reserved property' => [
            '{"_artifact":{}}',
            'Publication manifest cannot contain the reserved "_artifact" property.',
        ];
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

    private function artifact(): PublicationArtifact
    {
        return new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            'application/zip'
        );
    }
}
