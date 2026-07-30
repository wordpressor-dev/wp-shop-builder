<?php

declare(strict_types=1);

namespace WPShop\Core\Contracts;

use WPShop\Core\Provider\ServiceProviderRepository;

interface ProviderRegistryInterface
{
    public function addProvider(ServiceProviderInterface $provider): void;

    public function providers(): ServiceProviderRepository;
}
