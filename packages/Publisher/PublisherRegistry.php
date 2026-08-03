<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;
use WPShop\Publisher\Contracts\PublisherInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Exception\PublisherAlreadyRegistered;
use WPShop\Publisher\Exception\PublisherNotFound;

final class PublisherRegistry implements PublisherRegistryInterface
{
    /**
     * @var array<string, PublisherInterface>
     */
    private array $publishers = [];

    public function register(
        string $blueprintType,
        PublisherInterface $publisher
    ): void {
        $this->assertBlueprintType($blueprintType);

        if (isset($this->publishers[$blueprintType])) {
            throw PublisherAlreadyRegistered::forBlueprintType(
                $blueprintType
            );
        }

        $this->publishers[$blueprintType] = $publisher;
    }

    public function publisherFor(
        string $blueprintType
    ): PublisherInterface {
        $this->assertBlueprintType($blueprintType);

        $publisher = $this->publishers[$blueprintType] ?? null;

        if (! $publisher instanceof PublisherInterface) {
            throw PublisherNotFound::forBlueprintType(
                $blueprintType
            );
        }

        return $publisher;
    }

    private function assertBlueprintType(
        string $blueprintType
    ): void {
        if (trim($blueprintType) === '') {
            throw new InvalidArgumentException(
                'Publisher Blueprint type cannot be empty.'
            );
        }
    }
}
