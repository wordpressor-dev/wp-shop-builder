<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use DateTimeImmutable;
use UnexpectedValueException;
use WPShop\Release\Release;

final readonly class ReleaseRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): Release
    {
        return new Release(
            $this->positiveInteger(
                $row,
                'id'
            ),
            $this->positiveInteger(
                $row,
                'blueprint_id'
            ),
            $this->string(
                $row,
                'version'
            ),
            $this->string(
                $row,
                'status'
            ),
            $this->nullablePositiveInteger(
                $row,
                'manifest_id'
            ),
            $this->booleanFlag(
                $row,
                'published'
            ),
            $this->nullableValidationScore(
                $row,
                'validation_score'
            ),
            $this->dateTime(
                $row,
                'created_at'
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
        $value = $this->value(
            $row,
            $field
        );

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
        $value = $this->value(
            $row,
            $field
        );

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
        $value = $this->value(
            $row,
            $field
        );

        if (! is_string($value)) {
            throw $this->invalidField($field);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function booleanFlag(
        array $row,
        string $field
    ): bool {
        $value = $this->value(
            $row,
            $field
        );

        if (
            $value === 0
            || $value === '0'
        ) {
            return false;
        }

        if (
            $value === 1
            || $value === '1'
        ) {
            return true;
        }

        throw $this->invalidField($field);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableValidationScore(
        array $row,
        string $field
    ): ?float {
        $value = $this->value(
            $row,
            $field
        );

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $score = (float) $value;
        } elseif (is_float($value)) {
            $score = $value;
        } elseif (
            is_string($value)
            && preg_match(
                '/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D',
                $value
            ) === 1
        ) {
            $score = (float) $value;
        } else {
            throw $this->invalidField($field);
        }

        if (
            ! is_finite($score)
            || $score < 0.0
            || $score > 100.0
        ) {
            throw $this->invalidField($field);
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(
        array $row,
        string $field
    ): DateTimeImmutable {
        $value = $this->string(
            $row,
            $field
        );

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
            || $date->format(
                'Y-m-d H:i:s'
            ) !== $value
        ) {
            throw $this->invalidField($field);
        }

        return $date;
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
                'Release database field "%s" is invalid.',
                $field
            )
        );
    }
}
