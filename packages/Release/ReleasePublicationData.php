<?php

declare(strict_types=1);

namespace WPShop\Release;

use InvalidArgumentException;
use JsonException;

final readonly class ReleasePublicationData
{
    public function __construct(
        private int $releaseId,
        private string $manifestJson,
        private ?float $validationScore
    ) {
        $this->assertPositiveReleaseId($releaseId);
        $this->assertValidManifestJson($manifestJson);

        $this->assertValidationScore($validationScore);
    }

    public function releaseId(): int
    {
        return $this->releaseId;
    }

    public function manifestJson(): string
    {
        return $this->manifestJson;
    }

    public function validationScore(): ?float
    {
        return $this->validationScore;
    }

    private function assertPositiveReleaseId(
        int $releaseId
    ): void {
        if ($releaseId < 1) {
            throw new InvalidArgumentException(
                'Release publication releaseId must be a positive integer.'
            );
        }
    }

    private function assertValidManifestJson(
        string $manifestJson
    ): void {
        if (trim($manifestJson) === '') {
            throw new InvalidArgumentException(
                'Release publication manifestJson cannot be empty.'
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
                'Release publication manifestJson must contain valid JSON.',
                0,
                $exception
            );
        }
    }

    private function assertValidationScore(
        ?float $validationScore
    ): void {
        if ($validationScore === null) {
            return;
        }

        if (
            ! is_finite($validationScore)
            || $validationScore < 0.0
            || $validationScore > 100.0
        ) {
            throw new InvalidArgumentException(
                'Release publication validationScore must be between 0 and 100.'
            );
        }
    }
}
