<?php

declare(strict_types=1);

namespace WPShop\Manifest;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class Manifest
{
    public function __construct(
        private int $id,
        private int $releaseId,
        private string $manifestJson,
        private string $manifestHash,
        private DateTimeImmutable $createdAt
    ) {
        $this->assertPositiveId($id, 'id');

        $this->assertPositiveId($releaseId, 'releaseId');

        $this->assertManifestJson($manifestJson);
        $this->assertManifestHash($manifestHash);
    }

    public function id(): int
    {
        return $this->id;
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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function assertPositiveId(
        int $value,
        string $field
    ): void {
        if ($value < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Manifest %s must be a positive integer.',
                    $field
                )
            );
        }
    }

    private function assertManifestJson(
        string $manifestJson
    ): void {
        if (trim($manifestJson) === '') {
            throw new InvalidArgumentException(
                'Manifest manifestJson cannot be empty.'
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
                'Manifest manifestJson must contain valid JSON.',
                0,
                $exception
            );
        }
    }

    private function assertManifestHash(
        string $manifestHash
    ): void {
        if (
            preg_match(
                '/^[a-fA-F0-9]{64}$/D',
                $manifestHash
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Manifest manifestHash must contain 64 hexadecimal characters.'
            );
        }
    }
}
