<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Manifest;

use DateTimeImmutable;
use JsonException;
use UnexpectedValueException;
use WPShop\Manifest\Manifest;

final readonly class ManifestRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): Manifest
    {
        return new Manifest(
            $this->positiveInteger(
                $row,
                'id'
            ),
            $this->positiveInteger(
                $row,
                'release_id'
            ),
            $this->manifestJson(
                $row,
                'manifest_json'
            ),
            $this->manifestHash(
                $row,
                'manifest_hash'
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
            && (!is_string($value) || preg_match(
                '/^[1-9][0-9]*$/D',
                $value
            ) !== 1)
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
    private function manifestJson(
        array $row,
        string $field
    ): string {
        $value = $this->string(
            $row,
            $field
        );

        if (trim($value) === '') {
            throw $this->invalidField($field);
        }

        try {
            json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw $this->invalidField($field);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function manifestHash(
        array $row,
        string $field
    ): string {
        $value = $this->string(
            $row,
            $field
        );

        if (
            preg_match(
                '/^[a-fA-F0-9]{64}$/D',
                $value
            ) !== 1
        ) {
            throw $this->invalidField($field);
        }

        return $value;
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
                'Manifest database field "%s" is invalid.',
                $field
            )
        );
    }
}
