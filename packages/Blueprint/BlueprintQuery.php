<?php

declare(strict_types=1);

namespace WPShop\Blueprint;

use InvalidArgumentException;

final readonly class BlueprintQuery
{
    public const SORT_ID = 'id';

    public const SORT_SLUG = 'slug';

    public const SORT_TYPE = 'type';

    public const SORT_STATE = 'state';

    public const SORT_WORKFLOW = 'workflow';

    public const SORT_CREATED_AT = 'createdAt';

    public const SORT_UPDATED_AT = 'updatedAt';

    public const DIRECTION_ASCENDING = 'asc';

    public const DIRECTION_DESCENDING = 'desc';

    private const MAXIMUM_LIMIT = 100;

    /**
     * @var list<string>
     */
    private const SORT_FIELDS = [
        self::SORT_ID,
        self::SORT_SLUG,
        self::SORT_TYPE,
        self::SORT_STATE,
        self::SORT_WORKFLOW,
        self::SORT_CREATED_AT,
        self::SORT_UPDATED_AT,
    ];

    /**
     * @var list<string>
     */
    private const SORT_DIRECTIONS = [
        self::DIRECTION_ASCENDING,
        self::DIRECTION_DESCENDING,
    ];

    public function __construct(
        private ?string $type = null,
        private ?string $state = null,
        private ?string $workflow = null,
        private bool $includingDeleted = false,
        private string $sortBy = self::SORT_ID,
        private string $sortDirection = self::DIRECTION_DESCENDING,
        private int $limit = 50,
        private int $offset = 0
    ) {
        self::assertOptionalText(
            $type,
            'type',
            64
        );

        self::assertOptionalText(
            $state,
            'state',
            64
        );

        self::assertOptionalText(
            $workflow,
            'workflow',
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
                'Blueprint query sort field is invalid.'
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
                'Blueprint query sort direction is invalid.'
            );
        }

        if (
            $limit < 1
            || $limit > self::MAXIMUM_LIMIT
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Blueprint query limit must be between 1 and %d.',
                    self::MAXIMUM_LIMIT
                )
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Blueprint query offset cannot be negative.'
            );
        }
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function workflow(): ?string
    {
        return $this->workflow;
    }

    public function includingDeleted(): bool
    {
        return $this->includingDeleted;
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
                    'Blueprint query %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }
}
