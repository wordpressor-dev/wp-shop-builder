<?php

declare(strict_types=1);

namespace WPShop\Manifest;

use InvalidArgumentException;

final readonly class ManifestPage
{
    /**
     * @param list<Manifest> $items
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $limit,
        private int $offset
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException(
                'Manifest page total cannot be negative.'
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Manifest page limit must be positive.'
            );
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(
                'Manifest page offset cannot be negative.'
            );
        }

        if (count($items) > $limit) {
            throw new InvalidArgumentException(
                'Manifest page items cannot exceed the page limit.'
            );
        }

        if (count($items) > $total) {
            throw new InvalidArgumentException(
                'Manifest page items cannot exceed the total.'
            );
        }
    }

    /**
     * @return list<Manifest>
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
