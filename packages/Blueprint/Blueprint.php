<?php

declare(strict_types=1);

namespace WPShop\Blueprint;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Blueprint
{
    public function __construct(
        private int $id,
        private string $uuid,
        private string $slug,
        private string $type,
        private ?int $providerId,
        private ?int $developerId,
        private ?int $currentReleaseId,
        private string $state,
        private string $workflow,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt
    ) {
        self::assertPositiveId($id, 'id');
        self::assertUuid($uuid);
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

    public function id(): int
    {
        return $this->id;
    }

    public function uuid(): string
    {
        return $this->uuid;
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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    private static function assertUuid(string $uuid): void
    {
        if (
            preg_match(
                '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}'
                . '[0-9a-f]{12}$/Di',
                $uuid
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Blueprint UUID is invalid.'
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
                    'Blueprint %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }

    private static function assertPositiveId(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Blueprint %s must be a positive integer.',
                    $field
                )
            );
        }
    }

    private static function assertOptionalPositiveId(
        ?int $value,
        string $field
    ): void {
        if ($value !== null) {
            self::assertPositiveId($value, $field);
        }
    }
}
