<?php

declare(strict_types=1);

namespace WPShop\Core\Provider;

use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Contracts\ServiceProviderInterface;
use WPShop\Core\Provider\Exception\ServiceProviderAlreadyRegistered;
use WPShop\Core\Provider\Exception\ServiceProvidersNotRegistered;

final class ServiceProviderRepository
{
    /**
     * @var array<class-string<ServiceProviderInterface>, ServiceProviderInterface>
     */
    private array $providers = [];

    private bool $registered = false;

    private bool $booted = false;

    public function add(ServiceProviderInterface $provider): void
    {
        $providerClass = $provider::class;

        if ($this->has($providerClass)) {
            throw ServiceProviderAlreadyRegistered::forClass($providerClass);
        }

        if ($this->registered) {
            throw new \LogicException('Service providers cannot be added after registration has started.');
        }

        $this->providers[$providerClass] = $provider;
    }

    /**
     * @param class-string<ServiceProviderInterface> $providerClass
     */
    public function has(string $providerClass): bool
    {
        return isset($this->providers[$providerClass]);
    }

    /**
     * @return list<ServiceProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function registerAll(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->register();
        }

        $this->registered = true;
    }

    public function bootAll(KernelInterface $kernel): void
    {
        if ($this->booted) {
            return;
        }

        if (!$this->registered) {
            throw ServiceProvidersNotRegistered::beforeBoot();
        }

        foreach ($this->providers as $provider) {
            $provider->boot($kernel);
        }

        $this->booted = true;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
