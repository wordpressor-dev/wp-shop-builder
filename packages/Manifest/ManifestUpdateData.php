<?php

declare(strict_types=1);

namespace WPShop\Manifest;

use InvalidArgumentException;
use JsonException;

final readonly class ManifestUpdateData
{
    private string $manifestHash;

    public function __construct(
        private string $manifestJson
    ) {
        $this->assertValidJson($manifestJson);

        $this->manifestHash = hash(
            'sha256',
            $manifestJson
        );
    }

    public function manifestJson(): string
    {
        return $this->manifestJson;
    }

    public function manifestHash(): string
    {
        return $this->manifestHash;
    }

    private function assertValidJson(
        string $manifestJson
    ): void {
        if (trim($manifestJson) === '') {
            throw new InvalidArgumentException(
                'Manifest JSON cannot be empty.'
            );
        }

        try {
            json_decode(
                $manifestJson,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Manifest JSON is invalid.',
                0,
                $exception
            );
        }
    }
}
