<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database\Contracts;

interface DatabaseConnectionInterface
{
    /**
     * @param array<string, int|float|string|null> $data
     * @param list<string> $formats
     */
    public function insert(
        string $table,
        array $data,
        array $formats
    ): int;

    /**
     * @param array<string, int|float|string|null> $data
     * @param array<string, int|float|string|null> $where
     * @param list<string> $formats
     * @param list<string> $whereFormats
     */
    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int;

    /**
     * @param list<int|float|string> $parameters
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array;
}
