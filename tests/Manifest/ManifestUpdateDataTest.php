<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\ManifestUpdateData;

final class ManifestUpdateDataTest extends TestCase
{
    public function testExposesUpdateData(): void
    {
        $manifestJson = <<<'JSON'
{
    "name": "example-plugin",
    "version": "2.0.0"
}
JSON;

        $data = new ManifestUpdateData(
            $manifestJson
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

    public function testRejectsEmptyManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest JSON cannot be empty.'
        );

        new ManifestUpdateData('   ');
    }

    public function testRejectsMalformedManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest JSON is invalid.'
        );

        new ManifestUpdateData(
            '{"name":}'
        );
    }
}
