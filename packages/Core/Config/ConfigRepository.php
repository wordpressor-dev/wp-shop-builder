<?php

declare(strict_types=1);

namespace WPShop\Core\Config;

final class ConfigRepository implements ConfigInterface
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly array $items = []
    ) {
    }

    public function has(string $key): bool
    {
        $found = false;
        $this->resolve($key, $found);

        return $found;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $found = false;
        $value = $this->resolve($key, $found);

        return $found ? $value : $default;
    }

    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param array<string, mixed> $items
     */
    public function merge(array $items): self
    {
        return new self(self::mergeRecursiveDistinct($this->items, $items));
    }

    /**
     * @param-out bool $found
     */
    private function resolve(string $key, bool &$found): mixed
    {
        if ($key === '') {
            $found = true;

            return $this->items;
        }

        if (array_key_exists($key, $this->items)) {
            $found = true;

            return $this->items[$key];
        }

        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;

                return null;
            }

            $value = $value[$segment];
        }

        $found = true;

        return $value;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function mergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
            ) {
                if (array_is_list($base[$key]) || array_is_list($value)) {
                    $base[$key] = $value;
                } else {
                    $base[$key] = self::mergeRecursiveDistinct($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
