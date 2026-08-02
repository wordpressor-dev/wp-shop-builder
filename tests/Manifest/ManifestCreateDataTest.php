<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\ManifestCreateData;

final class ManifestCreateDataTest extends TestCase
{
    public function testExposesCreationData(): void
    {
        $manifestJson = <<<'JSON'
{
    "name": "example-plugin",
    "version": "1.2.3"
}
JSON;

        $data = new ManifestCreateData(
            7,
            $manifestJson
        );

        self::assertSame(
            7,
            $data->releaseId()
        );

        self::assertSame(
            $manifestJson,
            $data->manifestJson()
        );

        self::assertSame(
            hash('sha256', $manifestJson),
            $data->manifestHash()
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/D',
            $data->manifestHash()
        );
    }

    #[DataProvider('invalidReleaseIdentifierProvider')]
    public function testRejectsInvalidReleaseIdentifier(
        int $releaseId
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest releaseId must be a positive integer.'
        );

        new ManifestCreateData(
            $releaseId,
            '{}'
        );
    }

    public function testRejectsEmptyManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest JSON cannot be empty.'
        );

        new ManifestCreateData(
            7,
            '   '
        );
    }

    public function testRejectsMalformedManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest JSON is invalid.'
        );

        new ManifestCreateData(
            7,
            '{"name":}'
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidReleaseIdentifierProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
