<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Database;

use Closure;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Database\Exception\DatabaseOperationFailed;

final readonly class WordPressDatabaseConnection implements
    DatabaseConnectionInterface
{
    /**
     * @param Closure(
     *     string,
     *     array<string, int|float|string|null>,
     *     list<string>
     * ): int $insert
     * @param Closure(
     *     string,
     *     list<int|float|string>
     * ): string $prepare
     * @param Closure(string): (array<string, mixed>|null) $fetchOne
     * @param null|Closure(
     *     string,
     *     array<string, int|float|string|null>,
     *     array<string, int|float|string|null>,
     *     list<string>,
     *     list<string>
     * ): int $update
     */
    public function __construct(
        private Closure $insert,
        private Closure $prepare,
        private Closure $fetchOne,
        private ?Closure $update = null
    ) {
    }

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        if (trim($table) === '') {
            throw new InvalidArgumentException(
                'Database table name cannot be empty.'
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'Database insert data cannot be empty.'
            );
        }

        if (count($data) !== count($formats)) {
            throw new InvalidArgumentException(
                'Database insert formats must match data fields.'
            );
        }

        try {
            $insertId = ($this->insert)(
                $table,
                $data,
                $formats
            );

            if ($insertId < 1) {
                throw new UnexpectedValueException(
                    'Database insert did not return a valid identifier.'
                );
            }

            return $insertId;
        } catch (Throwable $exception) {
            throw DatabaseOperationFailed::operation(
                'insert',
                $exception
            );
        }
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        if (trim($table) === '') {
            throw new InvalidArgumentException(
                'Database table name cannot be empty.'
            );
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'Database update data cannot be empty.'
            );
        }

        if ($where === []) {
            throw new InvalidArgumentException(
                'Database update conditions cannot be empty.'
            );
        }

        if (count($data) !== count($formats)) {
            throw new InvalidArgumentException(
                'Database update formats must match data fields.'
            );
        }

        if (count($where) !== count($whereFormats)) {
            throw new InvalidArgumentException(
                'Database condition formats must match condition fields.'
            );
        }

        try {
            if ($this->update === null) {
                throw new UnexpectedValueException(
                    'Database update operation is not configured.'
                );
            }

            $affectedRows = ($this->update)(
                $table,
                $data,
                $where,
                $formats,
                $whereFormats
            );

            if ($affectedRows < 0) {
                throw new UnexpectedValueException(
                    'Database update returned an invalid result.'
                );
            }

            return $affectedRows;
        } catch (Throwable $exception) {
            throw DatabaseOperationFailed::operation(
                'update',
                $exception
            );
        }
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        if (trim($sql) === '') {
            throw new InvalidArgumentException(
                'Database query cannot be empty.'
            );
        }

        try {
            $preparedSql = $sql;

            if ($parameters !== []) {
                $preparedSql = ($this->prepare)(
                    $sql,
                    $parameters
                );

                if (trim($preparedSql) === '') {
                    throw new UnexpectedValueException(
                        'Database query preparation returned an empty query.'
                    );
                }
            }

            return ($this->fetchOne)($preparedSql);
        } catch (Throwable $exception) {
            throw DatabaseOperationFailed::operation(
                'fetch one',
                $exception
            );
        }
    }
}
