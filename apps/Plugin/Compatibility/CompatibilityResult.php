<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Compatibility;

final readonly class CompatibilityResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private array $errors
    ) {
    }

    public function isCompatible(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function message(): string
    {
        return implode(' ', $this->errors);
    }
}