<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Exception\ManifestNotFound;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestUpdateData;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;
use WPShop\Release\ReleaseUpdateData;

final readonly class ReleasePublicationService implements
    ReleasePublicationServiceInterface
{
    public function __construct(
        private ReleaseRepositoryInterface $releaseRepository,
        private BlueprintRepositoryInterface $blueprintRepository,
        private ManifestRepositoryInterface $manifestRepository,
        private TransactionManagerInterface $transactionManager
    ) {
    }

    public function publish(
        ReleasePublicationData $data
    ): Release {
        return $this->transactionManager->transactional(
            fn (): Release =>
                $this->publishWithinTransaction($data)
        );
    }

    private function publishWithinTransaction(
        ReleasePublicationData $data
    ): Release {
        $release = $this->releaseRepository->findById(
            $data->releaseId()
        );

        if (! $release instanceof Release) {
            throw ReleaseNotFound::byId(
                $data->releaseId()
            );
        }

        $blueprint = $this->blueprintRepository->findById(
            $release->blueprintId()
        );

        if (! $blueprint instanceof Blueprint) {
            throw BlueprintNotFound::byId(
                $release->blueprintId()
            );
        }

        $manifest = $this->persistManifest(
            $release,
            $data
        );

        $publishedRelease =
            $this->releaseRepository->update(
                $release->id(),
                new ReleaseUpdateData(
                    $release->version(),
                    'published',
                    $manifest->id(),
                    true,
                    $data->validationScore()
                )
            );

        if (! $publishedRelease instanceof Release) {
            throw ReleaseNotFound::byId(
                $release->id()
            );
        }

        $updatedBlueprint =
            $this->blueprintRepository->update(
                $blueprint->id(),
                new BlueprintUpdateData(
                    $blueprint->slug(),
                    $blueprint->type(),
                    $blueprint->providerId(),
                    $blueprint->developerId(),
                    $publishedRelease->id(),
                    $blueprint->state(),
                    $blueprint->workflow()
                )
            );

        if (! $updatedBlueprint instanceof Blueprint) {
            throw BlueprintNotFound::byId(
                $blueprint->id()
            );
        }

        return $publishedRelease;
    }

    private function persistManifest(
        Release $release,
        ReleasePublicationData $data
    ): Manifest {
        $manifest =
            $this->manifestRepository
                ->findByReleaseId(
                    $release->id()
                );

        if (! $manifest instanceof Manifest) {
            return $this->manifestRepository->create(
                new ManifestCreateData(
                    $release->id(),
                    $data->manifestJson()
                )
            );
        }

        $updatedManifest =
            $this->manifestRepository->update(
                $manifest->id(),
                new ManifestUpdateData(
                    $data->manifestJson()
                )
            );

        if (! $updatedManifest instanceof Manifest) {
            throw ManifestNotFound::byId(
                $manifest->id()
            );
        }

        return $updatedManifest;
    }
}
