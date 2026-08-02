<?php

declare(strict_types=1);

namespace WPShop\Manifest;

use InvalidArgumentException;

final readonly class ManifestQuery
{
    public const string SORT_ID = 'id';

    public const string SORT_RELEASE_ID = 'releaseId';

    public const string SORT_MANIFEST_HASH = 'manifestHash';

    public const string SORT_CREATED_AT = 'createdAt';

    public const string DIRECTION_ASCENDING = 'asc';

    public const string DIRECTION_DESCENDING = 'desc';

    private const int MAXIMUM_LIMIT = 100;

    /**
     * @var list<string>
     */
    private const array SORT_FIELDS = [
        self::SORT_ID,
        self::SORT_RELEASE_ID,
        self::SORT_MANIFEST_HASH,
        self::SORT_CREATED_AT,
    ];

    /**
     * @var list<string>
     */
    private const array SORT_DIRECTIONS = [
        self::DIRECTION_ASCENDING,
        self::DIRECTION_DESCENDING,
    ];

    public function __construct(
        private ?int $releaseId = null,
        private ?string $manifestHash = null,
        private string $sortBy = self::SORT_ID,
        private string $sortDirection = self::DIRECTION_DESCENDING,
        private int $limit = 50,
        private int $offset = 0
    ) {
        if (
            $releaseId !== null
            && $releaseId < 1
        ) {
            throw new InvalidArgumentException(
                'Manifest query release identifier must be positive.'
            );
        }

        $this->assertOptionalManifestHash($manifestHash);

        if (
            ! in_array(
                $sortBy,
                self::SORT_FIELDS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Manifest query sort field is invalid.'
            );
        }

        if (
            ! in_array(
                $sortDirection,
                self::SORT_DIRECTIONS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Manifest query sort direction is invalid.'
            );
        }

        if (
            $limit < 1
            || $limit > self::MAXIMUM_LIMIT
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Manifest query limit must be between 1 and %d.',
                    self::MAXIMUM_LIMIT
                )
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Manifest query offset cannot be negative.'
            );
        }
    }

    public function releaseId(): ?int
    {
        return $this->releaseId;
    }

    public function manifestHash(): ?string
    {
        return $this->manifestHash;
    }

    public function sortBy(): string
    {
        return $this->sortBy;
    }

    public function sortDirection(): string
    {
        return $this->sortDirection;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    private function assertOptionalManifestHash(
        ?string $manifestHash
    ): void {
        if ($manifestHash === null) {
            return;
        }

        if (
            preg_match(
                '/^[a-fA-F0-9]{64}$/D',
                $manifestHash
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Manifest query manifest hash must contain '
                . '64 hexadecimal characters.'
            );
        }
    }
}
