<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;

final readonly class PluginHeader
{
    private string $name;

    private string $version;

    private ?string $requiresAtLeast;

    private ?string $requiresPhp;

    /**
     * @var list<string>
     */
    private array $requiredPlugins;

    private ?string $textDomain;

    /**
     * @param list<string> $requiredPlugins
     */
    public function __construct(
        string $name,
        string $version,
        ?string $requiresAtLeast = null,
        ?string $requiresPhp = null,
        array $requiredPlugins = [],
        ?string $textDomain = null
    ) {
        $this->name = $this->requiredText(
            $name,
            'name'
        );

        $this->version = $this->requiredText(
            $version,
            'version'
        );

        $this->requiresAtLeast = $this->optionalText(
            $requiresAtLeast,
            'requiresAtLeast'
        );

        $this->requiresPhp = $this->optionalText(
            $requiresPhp,
            'requiresPhp'
        );

        $this->requiredPlugins = $this->plugins(
            $requiredPlugins
        );

        $this->textDomain = $this->optionalText(
            $textDomain,
            'textDomain'
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

    public function requiresAtLeast(): ?string
    {
        return $this->requiresAtLeast;
    }

    public function requiresPhp(): ?string
    {
        return $this->requiresPhp;
    }

    /**
     * @return list<string>
     */
    public function requiredPlugins(): array
    {
        return $this->requiredPlugins;
    }

    public function textDomain(): ?string
    {
        return $this->textDomain;
    }

    private function requiredText(
        string $value,
        string $field
    ): string {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException(
                sprintf(
                    'Plugin header %s cannot be empty.',
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

    /**
     * @param list<string> $plugins
     *
     * @return list<string>
     */
    private function plugins(array $plugins): array
    {
        $normalized = [];

        foreach ($plugins as $plugin) {
            $value = trim($plugin);

            if ($value === '') {
                throw new InvalidArgumentException(
                    'Plugin header requiredPlugins '
                    . 'cannot contain an empty value.'
                );
            }

            $this->assertNoNullByte(
                $value,
                'requiredPlugins'
            );

            $normalized[] = $value;
        }

        return $normalized;
    }

    private function assertNoNullByte(
        string $value,
        string $field
    ): void {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException(
                sprintf(
                    'Plugin header %s cannot contain null bytes.',
                    $field
                )
            );
        }
    }
}
