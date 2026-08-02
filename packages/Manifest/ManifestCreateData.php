<?php

declare(strict_types=1);

namespace WPShop\Manifest;

use InvalidArgumentException;
use JsonException;

final readonly class ManifestCreateData
{
    private string $manifestHash;

    public function __construct(
        private int $releaseId,
        private string $manifestJson
    ) {
        $this->assertPositiveReleaseId($releaseId);

        $this->assertValidJson($manifestJson);

        $this->manifestHash = hash(
            'sha256',
            $manifestJson
        );
    }

    public function releaseId(): int
    {
        return $this->releaseId;
    }

    public function manifestJson(): string
    {
        return $this->manifestJson;
    }

    public function manifestHash(): string
    {
        return $this->manifestHash;
    }

    private function assertPositiveReleaseId(
        int $releaseId
    ): void {
        if ($releaseId < 1) {
            throw new InvalidArgumentException(
                'Manifest releaseId must be a positive integer.'
            );
        }
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
