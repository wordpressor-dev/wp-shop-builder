<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Service;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;

final readonly class BlueprintService implements
    BlueprintServiceInterface
{
    public function __construct(
        private BlueprintRepositoryInterface $repository
    ) {
    }

    public function create(
        BlueprintCreateData $data
    ): Blueprint {
        return $this->repository->create($data);
    }

    public function update(
        int $id,
        BlueprintUpdateData $data
    ): Blueprint {
        $blueprint = $this->repository->update(
            $id,
            $data
        );

        if ($blueprint === null) {
            throw BlueprintNotFound::byId($id);
        }

        return $blueprint;
    }

    public function delete(int $id): void
    {
        if (! $this->repository->softDelete($id)) {
            throw BlueprintNotFound::byId($id);
        }
    }

    public function getById(int $id): Blueprint
    {
        $blueprint = $this->repository->findById($id);

        if ($blueprint === null) {
            throw BlueprintNotFound::byId($id);
        }

        return $blueprint;
    }

    public function getByUuid(string $uuid): Blueprint
    {
        $blueprint = $this->repository->findByUuid(
            $uuid
        );

        if ($blueprint === null) {
            throw BlueprintNotFound::byUuid($uuid);
        }

        return $blueprint;
    }
}
