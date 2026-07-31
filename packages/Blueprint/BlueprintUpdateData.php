<?php

declare(strict_types=1);

namespace WPShop\Blueprint;

use InvalidArgumentException;

final readonly class BlueprintUpdateData
{
    public function __construct(
        private string $slug,
        private string $type,
        private ?int $providerId,
        private ?int $developerId,
        private ?int $currentReleaseId,
        private string $state,
        private string $workflow
    ) {
        self::assertText($slug, 'slug', 191);
        self::assertText($type, 'type', 64);

        self::assertOptionalPositiveId(
            $providerId,
            'providerId'
        );

        self::assertOptionalPositiveId(
            $developerId,
            'developerId'
        );

        self::assertOptionalPositiveId(
            $currentReleaseId,
            'currentReleaseId'
        );

        self::assertText($state, 'state', 64);
        self::assertText($workflow, 'workflow', 64);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function providerId(): ?int
    {
        return $this->providerId;
    }

    public function developerId(): ?int
    {
        return $this->developerId;
    }

    public function currentReleaseId(): ?int
    {
        return $this->currentReleaseId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function workflow(): string
    {
        return $this->workflow;
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
                    'Blueprint %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }

    private static function assertOptionalPositiveId(
        ?int $value,
        string $field
    ): void {
        if ($value !== null && $value < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Blueprint %s must be a positive integer.',
                    $field
                )
            );
        }
    }
}
