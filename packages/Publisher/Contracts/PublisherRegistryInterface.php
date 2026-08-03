<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

interface PublisherRegistryInterface
{
    public function register(
        string $blueprintType,
        PublisherInterface $publisher
    ): void;

    public function publisherFor(
        string $blueprintType
    ): PublisherInterface;
}
