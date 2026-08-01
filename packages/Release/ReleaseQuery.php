<?php

declare(strict_types=1);

namespace WPShop\Release;

use InvalidArgumentException;

final readonly class ReleaseQuery
{
    public const SORT_ID = 'id';

    public const SORT_BLUEPRINT_ID = 'blueprintId';

    public const SORT_VERSION = 'version';

    public const SORT_STATUS = 'status';

    public const SORT_PUBLISHED = 'published';

    public const SORT_VALIDATION_SCORE =
        'validationScore';

    public const SORT_CREATED_AT = 'createdAt';

    public const DIRECTION_ASCENDING = 'asc';

    public const DIRECTION_DESCENDING = 'desc';

    private const MAXIMUM_LIMIT = 100;

    /**
     * @var list<string>
     */
    private const SORT_FIELDS = [
        self::SORT_ID,
        self::SORT_BLUEPRINT_ID,
        self::SORT_VERSION,
        self::SORT_STATUS,
        self::SORT_PUBLISHED,
        self::SORT_VALIDATION_SCORE,
        self::SORT_CREATED_AT,
    ];

    /**
     * @var list<string>
     */
    private const SORT_DIRECTIONS = [
        self::DIRECTION_ASCENDING,
        self::DIRECTION_DESCENDING,
    ];

    public function __construct(
        private ?int $blueprintId = null,
        private ?string $status = null,
        private ?bool $published = null,
        private string $sortBy = self::SORT_ID,
        private string $sortDirection = self::DIRECTION_DESCENDING,
        private int $limit = 50,
        private int $offset = 0
    ) {
        if (
            $blueprintId !== null
            && $blueprintId < 1
        ) {
            throw new InvalidArgumentException(
                'Release query blueprint identifier must be positive.'
            );
        }

        self::assertOptionalText(
            $status,
            'status',
            64
        );

        if (
            ! in_array(
                $sortBy,
                self::SORT_FIELDS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Release query sort field is invalid.'
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
                'Release query sort direction is invalid.'
            );
        }

        if (
            $limit < 1
            || $limit > self::MAXIMUM_LIMIT
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release query limit must be between 1 and %d.',
                    self::MAXIMUM_LIMIT
                )
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Release query offset cannot be negative.'
            );
        }
    }

    public function blueprintId(): ?int
    {
        return $this->blueprintId;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function published(): ?bool
    {
        return $this->published;
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

    private static function assertOptionalText(
        ?string $value,
        string $field,
        int $maximumLength
    ): void {
        if ($value === null) {
            return;
        }

        if (
            trim($value) === ''
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release query %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }
}
