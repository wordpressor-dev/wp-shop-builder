<?php

declare(strict_types=1);

namespace WPShop\Release;

use InvalidArgumentException;

final readonly class ReleasePage
{
    /**
     * @param list<Release> $items
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $limit,
        private int $offset
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException(
                'Release page total cannot be negative.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Release page limit must be positive.'
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Release page offset cannot be negative.'
            );
        }

        if (count($items) > $limit) {
            throw new InvalidArgumentException(
                'Release page items cannot exceed the page limit.'
            );
        }

        if (count($items) > $total) {
            throw new InvalidArgumentException(
                'Release page items cannot exceed the total.'
            );
        }
    }

    /**
     * @return list<Release>
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
