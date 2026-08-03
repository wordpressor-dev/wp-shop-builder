<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleasePublisherServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;

final readonly class ReleasePublisherService implements
    ReleasePublisherServiceInterface
{
    public function __construct(
        private ReleaseRepositoryInterface $releaseRepository,
        private BlueprintRepositoryInterface $blueprintRepository,
        private ReleasePublicationPolicyInterface $publicationPolicy,
        private PublisherRegistryInterface $publisherRegistry,
        private ReleasePublicationServiceInterface $publicationService
    ) {
    }

    public function publish(int $releaseId): Release
    {
        $release = $this->releaseRepository->findById(
            $releaseId
        );

        if (! $release instanceof Release) {
            throw ReleaseNotFound::byId($releaseId);
        }

        $blueprint = $this->blueprintRepository->findById(
            $release->blueprintId()
        );

        if (! $blueprint instanceof Blueprint) {
            throw BlueprintNotFound::byId(
                $release->blueprintId()
            );
        }

        $this->publicationPolicy->assertCanPublish(
            $blueprint,
            $release
        );

        $publisher = $this->publisherRegistry->publisherFor(
            $blueprint->type()
        );

        $result = $publisher->publish(
            $blueprint,
            $release
        );

        return $this->publicationService->publish(
            new ReleasePublicationData(
                $release->id(),
                $result->manifestJson(),
                $result->validationScore()
            )
        );
    }
}
