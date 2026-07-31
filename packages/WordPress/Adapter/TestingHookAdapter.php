<?php

declare(strict_types=1);

namespace WPShop\WordPress\Adapter;

use WPShop\WordPress\Contracts\HookAdapterInterface;

final class TestingHookAdapter implements HookAdapterInterface
{
    /**
     * @var array<string, list<array{callback: callable, priority: int, accepted_args: int}>>
     */
    private array $actions = [];

    /**
     * @var array<string, list<array{callback: callable, priority: int, accepted_args: int}>>
     */
    private array $filters = [];

    public function addAction(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->actions[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    public function addFilter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->filters[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
    }

    /**
     * @return list<array{callback: callable, priority: int, accepted_args: int}>
     */
    public function actions(string $hook): array
    {
        return $this->sorted($this->actions[$hook] ?? []);
    }

    /**
     * @return list<array{callback: callable, priority: int, accepted_args: int}>
     */
    public function filters(string $hook): array
    {
        return $this->sorted($this->filters[$hook] ?? []);
    }

    public function hasAction(string $hook): bool
    {
        return $this->actions($hook) !== [];
    }

    public function hasFilter(string $hook): bool
    {
        return $this->filters($hook) !== [];
    }

    public function doAction(string $hook, mixed ...$arguments): void
    {
        foreach ($this->actions($hook) as $registration) {
            ($registration['callback'])(
                ...array_slice($arguments, 0, $registration['accepted_args'])
            );
        }
    }

    public function applyFilters(
        string $hook,
        mixed $value,
        mixed ...$arguments
    ): mixed {
        foreach ($this->filters($hook) as $registration) {
            $filterArguments = array_slice(
                [$value, ...$arguments],
                0,
                $registration['accepted_args']
            );

            $value = ($registration['callback'])(...$filterArguments);
        }

        return $value;
    }

    /**
     * @param list<array{callback: callable, priority: int, accepted_args: int}> $registrations
     *
     * @return list<array{callback: callable, priority: int, accepted_args: int}>
     */
    private function sorted(array $registrations): array
    {
        usort(
            $registrations,
            static fn (array $left, array $right): int =>
                $left['priority'] <=> $right['priority']
        );

        return $registrations;
    }
}
