<?php

declare(strict_types=1);

namespace WPShop\Blueprint;

use InvalidArgumentException;

final readonly class BlueprintPage
{
    /**
     * @param list<Blueprint> $items
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $limit,
        private int $offset
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException(
                'Blueprint page total cannot be negative.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Blueprint page limit must be positive.'
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Blueprint page offset cannot be negative.'
            );
        }

        if (count($items) > $limit) {
            throw new InvalidArgumentException(
                'Blueprint page items cannot exceed the page limit.'
            );
        }

        if (count($items) > $total) {
            throw new InvalidArgumentException(
                'Blueprint page items cannot exceed the total.'
            );
        }
    }

    /**
     * @return list<Blueprint>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function totalPages(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return intdiv(
            $this->total - 1,
            $this->limit
        ) + 1;
    }
}
