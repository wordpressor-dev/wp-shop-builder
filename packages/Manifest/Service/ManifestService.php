<?php

declare(strict_types=1);

namespace WPShop\Manifest\Service;

use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Manifest\Exception\ManifestNotFound;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestPage;
use WPShop\Manifest\ManifestQuery;
use WPShop\Manifest\ManifestUpdateData;

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

    public function update(
        int $id,
        ManifestUpdateData $data
    ): Manifest {
        $manifest = $this->repository->update(
            $id,
            $data
        );

        if (!$manifest instanceof \WPShop\Manifest\Manifest) {
            throw ManifestNotFound::byId($id);
        }

        return $manifest;
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

    public function getAll(
        ManifestQuery $query
    ): array {
        return $this->repository->findAll($query);
    }

    public function getPage(
        ManifestQuery $query
    ): ManifestPage {
        return $this->repository->findPage($query);
    }
}
