<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class ThemeHeader
{
    private string $name;

    private string $version;

    private ?string $testedUpTo;

    private ?string $requiresAtLeast;

    private ?string $requiresPhp;

    private ?string $textDomain;

    private ?string $template;

    public function __construct(
        string $name,
        string $version,
        ?string $testedUpTo = null,
        ?string $requiresAtLeast = null,
        ?string $requiresPhp = null,
        ?string $textDomain = null,
        ?string $template = null
    ) {
        $this->name = $this->requiredText(
            $name,
            'name'
        );

        $this->version = $this->requiredText(
            $version,
            'version'
        );

        $this->testedUpTo = $this->optionalText(
            $testedUpTo,
            'testedUpTo'
        );

        $this->requiresAtLeast = $this->optionalText(
            $requiresAtLeast,
            'requiresAtLeast'
        );

        $this->requiresPhp = $this->optionalText(
            $requiresPhp,
            'requiresPhp'
        );

        $this->textDomain = $this->optionalText(
            $textDomain,
            'textDomain'
        );

        $this->template = $this->optionalText(
            $template,
            'template'
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function testedUpTo(): ?string
    {
        return $this->testedUpTo;
    }

    public function requiresAtLeast(): ?string
    {
        return $this->requiresAtLeast;
    }

    public function requiresPhp(): ?string
    {
        return $this->requiresPhp;
    }

    public function textDomain(): ?string
    {
        return $this->textDomain;
    }

    public function template(): ?string
    {
        return $this->template;
    }

    private function requiredText(
        string $value,
        string $field
    ): string {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(
                sprintf(
                    'Theme header %s cannot be empty.',
                    $field
                )
            );
        }

        $this->assertNoNullByte(
            $normalized,
            $field
        );

        return $normalized;
    }

    private function optionalText(
        ?string $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $this->assertNoNullByte(
            $normalized,
            $field
        );

        return $normalized;
    }

    private function assertNoNullByte(
        string $value,
        string $field
    ): void {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException(
                sprintf(
                    'Theme header %s cannot contain null bytes.',
                    $field
                )
            );
        }
    }
}
