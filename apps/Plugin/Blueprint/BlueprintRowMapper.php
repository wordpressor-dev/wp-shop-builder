<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Blueprint;

use DateTimeImmutable;
use UnexpectedValueException;
use WPShop\Blueprint\Blueprint;

final readonly class BlueprintRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): Blueprint
    {
        return new Blueprint(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'uuid'),
            $this->string($row, 'slug'),
            $this->string($row, 'type'),
            $this->nullablePositiveInteger(
                $row,
                'provider_id'
            ),
            $this->nullablePositiveInteger(
                $row,
                'developer_id'
            ),
            $this->nullablePositiveInteger(
                $row,
                'current_release_id'
            ),
            $this->string($row, 'state'),
            $this->string($row, 'workflow'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->nullableDateTime(
                $row,
                'deleted_at'
            )
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function positiveInteger(
        array $row,
        string $field
    ): int {
        $value = $this->value($row, $field);

        if (
            ! is_int($value)
            && ! (
                is_string($value)
                && preg_match(
                    '/^[1-9][0-9]*$/D',
                    $value
                ) === 1
            )
        ) {
            throw $this->invalidField($field);
        }

        $integer = (int) $value;

        if ($integer < 1) {
            throw $this->invalidField($field);
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullablePositiveInteger(
        array $row,
        string $field
    ): ?int {
        $value = $this->value($row, $field);

        if ($value === null) {
            return null;
        }

        return $this->positiveInteger(
            [$field => $value],
            $field
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(
        array $row,
        string $field
    ): string {
        $value = $this->value($row, $field);

        if (! is_string($value)) {
            throw $this->invalidField($field);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(
        array $row,
        string $field
    ): DateTimeImmutable {
        $value = $this->string($row, $field);

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                $errors !== false
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw $this->invalidField($field);
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableDateTime(
        array $row,
        string $field
    ): ?DateTimeImmutable {
        $value = $this->value($row, $field);

        if ($value === null) {
            return null;
        }

        return $this->dateTime(
            [$field => $value],
            $field
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function value(
        array $row,
        string $field
    ): mixed {
        if (! array_key_exists($field, $row)) {
            throw $this->invalidField($field);
        }

        return $row[$field];
    }

    private function invalidField(
        string $field
    ): UnexpectedValueException {
        return new UnexpectedValueException(
            sprintf(
                'Blueprint database field "%s" is invalid.',
                $field
            )
        );
    }
}
