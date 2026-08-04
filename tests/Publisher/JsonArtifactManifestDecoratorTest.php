<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\Exception\InvalidArtifactManifest;
use WPShop\Publisher\Manifest\JsonArtifactManifestDecorator;
use WPShop\Publisher\StoredArtifact;

final class JsonArtifactManifestDecoratorTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testAddsStoredArtifactMetadata(): void
    {
        $decorated = (
            new JsonArtifactManifestDecorator()
        )->decorate(
            '{"name":"example","version":"1.0.0"}',
            $this->artifact()
        );

        self::assertSame(
            [
                'name' => 'example',
                'version' => '1.0.0',
                '_artifact' => [
                    'key' => $this->artifact()->storageKey(),
                    'filename' => 'package.zip',
                    'mediaType' => 'application/zip',
                    'size' => 7,
                    'sha256' => $this->artifact()->sha256(),
                ],
            ],
            json_decode(
                $decorated,
                true,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * @throws JsonException
     */
    public function testDecoratesEmptyJsonObject(): void
    {
        $decorated = (
            new JsonArtifactManifestDecorator()
        )->decorate(
            '{}',
            $this->artifact()
        );

        /** @var array{_artifact: array{key: string}} $manifest */
        $manifest = json_decode(
            $decorated,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            $this->artifact()->storageKey(),
            $manifest['_artifact']['key']
        );
    }

    #[DataProvider('invalidManifests')]
    public function testRejectsInvalidPublisherManifest(
        string $manifestJson,
        string $message
    ): void {
        $this->expectException(
            InvalidArtifactManifest::class
        );

        $this->expectExceptionMessage($message);

        (
            new JsonArtifactManifestDecorator()
        )->decorate(
            $manifestJson,
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
            '"manifest"',
            'Publication manifest JSON must contain an object.',
        ];

        yield 'reserved property' => [
            '{"_artifact":null}',
            'Publication manifest cannot contain the reserved "_artifact" property.',
        ];
    }

    private function artifact(): StoredArtifact
    {
        return new StoredArtifact(
            '123e4567-e89b-12d3-a456-426614174000/10/package.zip',
            'package.zip',
            'application/zip',
            7,
            hash(
                'sha256',
                'package'
            )
        );
    }
}
