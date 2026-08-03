<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;
use JsonException;

final readonly class PublicationResult
{
    public function __construct(
        private string $manifestJson,
        private ?float $validationScore
    ) {
        $this->assertValidManifestJson($manifestJson);
        $this->assertValidationScore($validationScore);
    }

    public function manifestJson(): string
    {
        return $this->manifestJson;
    }

    public function validationScore(): ?float
    {
        return $this->validationScore;
    }

    private function assertValidManifestJson(
        string $manifestJson
    ): void {
        if (trim($manifestJson) === '') {
            throw new InvalidArgumentException(
                'Publication result manifestJson cannot be empty.'
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
                'Publication result manifestJson must contain valid JSON.',
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
                'Publication result validationScore must be between 0 and 100.'
            );
        }
    }
}
