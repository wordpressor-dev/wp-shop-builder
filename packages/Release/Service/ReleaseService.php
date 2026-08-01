<?php

declare(strict_types=1);

namespace WPShop\Release\Service;

use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Contracts\ReleaseServiceInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;
use WPShop\Release\ReleasePage;
use WPShop\Release\ReleaseQuery;
use WPShop\Release\ReleaseUpdateData;

final readonly class ReleaseService implements
    ReleaseServiceInterface
{
    public function __construct(
        private ReleaseRepositoryInterface $repository
    ) {
    }

    public function create(
        ReleaseCreateData $data
    ): Release {
        return $this->repository->create($data);
    }

    public function update(
        int $id,
        ReleaseUpdateData $data
    ): Release {
        $release = $this->repository->update(
            $id,
            $data
        );

        if ($release === null) {
            throw ReleaseNotFound::byId($id);
        }

        return $release;
    }

    public function getById(int $id): Release
    {
        $release = $this->repository->findById($id);

        if ($release === null) {
            throw ReleaseNotFound::byId($id);
        }

        return $release;
    }

    public function getByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): Release {
        $release = $this->repository
            ->findByBlueprintAndVersion(
                $blueprintId,
                $version
            );

        if ($release === null) {
            throw ReleaseNotFound
                ::byBlueprintAndVersion(
                    $blueprintId,
                    $version
                );
        }

        return $release;
    }

    public function getAll(
        ReleaseQuery $query
    ): array {
        return $this->repository->findAll($query);
    }

    public function getPage(
        ReleaseQuery $query
    ): ReleasePage {
        return $this->repository->findPage($query);
    }
}
