<?php

declare(strict_types=1);

namespace WPShop\Manifest\Service;

use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Manifest\Exception\ManifestNotFound;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;

final readonly class ManifestService implements
    ManifestServiceInterface
{
    public function __construct(
        private ManifestRepositoryInterface $repository
    ) {
    }

    public function create(
        ManifestCreateData $data
    ): Manifest {
        return $this->repository->create($data);
    }

    public function getById(int $id): Manifest
    {
        $manifest = $this->repository->findById($id);

        if (!$manifest instanceof \WPShop\Manifest\Manifest) {
            throw ManifestNotFound::byId($id);
        }

        return $manifest;
    }

    public function getByReleaseId(
        int $releaseId
    ): Manifest {
        $manifest = $this->repository
            ->findByReleaseId($releaseId);

        if (!$manifest instanceof \WPShop\Manifest\Manifest) {
            throw ManifestNotFound::byReleaseId(
                $releaseId
            );
        }

        return $manifest;
    }
}
