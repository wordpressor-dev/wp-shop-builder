<?php

declare(strict_types=1);

namespace WPShop\Release;

use InvalidArgumentException;

final readonly class ReleaseUpdateData
{
    public function __construct(
        private string $version,
        private string $status,
        private ?int $manifestId,
        private bool $published,
        private ?float $validationScore
    ) {
        self::assertText(
            $version,
            'version',
            64
        );

        self::assertText(
            $status,
            'status',
            64
        );

        self::assertOptionalPositiveId(
            $manifestId,
            'manifestId'
        );

        self::assertValidationScore(
            $validationScore
        );
    }

    public function version(): string
    {
        return $this->version;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function manifestId(): ?int
    {
        return $this->manifestId;
    }

    public function published(): bool
    {
        return $this->published;
    }

    public function validationScore(): ?float
    {
        return $this->validationScore;
    }

    private static function assertOptionalPositiveId(
        ?int $value,
        string $field
    ): void {
        if ($value !== null && $value < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release %s must be a positive integer.',
                    $field
                )
            );
        }
    }

    private static function assertText(
        string $value,
        string $field,
        int $maximumLength
    ): void {
        if (
            trim($value) === ''
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }

    private static function assertValidationScore(
        ?float $score
    ): void {
        if ($score === null) {
            return;
        }

        if (
            ! is_finite($score)
            || $score < 0.0
            || $score > 100.0
        ) {
            throw new InvalidArgumentException(
                'Release validationScore must be between 0 and 100.'
            );
        }
    }
}
