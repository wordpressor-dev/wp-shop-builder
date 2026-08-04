<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use Throwable;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Publisher\Contracts\ArtifactManifestDecoratorInterface;
use WPShop\Publisher\Contracts\ArtifactStorageInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Exception\ArtifactCleanupFailed;
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
        private ArtifactStorageInterface $artifactStorage,
        private ArtifactManifestDecoratorInterface $artifactManifestDecorator,
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

        $storedArtifact = $this->artifactStorage->store(
            $blueprint,
            $release,
            $result->artifact()
        );

        try {
            $manifestJson =
                $this->artifactManifestDecorator->decorate(
                    $result->manifestJson(),
                    $storedArtifact
                );

            return $this->publicationService->publish(
                new ReleasePublicationData(
                    $release->id(),
                    $manifestJson,
                    $result->validationScore()
                )
            );
        } catch (Throwable $initialFailure) {
            try {
                $this->artifactStorage->delete(
                    $storedArtifact
                );
            } catch (Throwable $cleanupFailure) {
                throw ArtifactCleanupFailed::because(
                    $initialFailure,
                    $cleanupFailure
                );
            }

            throw $initialFailure;
        }
    }
}
