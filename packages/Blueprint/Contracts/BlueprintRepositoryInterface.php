<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\BlueprintPage;
use WPShop\Blueprint\BlueprintQuery;
use WPShop\Blueprint\BlueprintUpdateData;

interface BlueprintRepositoryInterface
{
    public function create(
        BlueprintCreateData $data
    ): Blueprint;

    public function update(
        int $id,
        BlueprintUpdateData $data
    ): ?Blueprint;

    public function softDelete(int $id): bool;

    public function restore(int $id): ?Blueprint;

    public function findById(int $id): ?Blueprint;

    public function findByUuid(string $uuid): ?Blueprint;

    public function findByIdIncludingDeleted(
        int $id
    ): ?Blueprint;

    public function findByUuidIncludingDeleted(
        string $uuid
    ): ?Blueprint;

    /**
     * @return list<Blueprint>
     */
    public function findAll(
        BlueprintQuery $query
    ): array;

    public function findPage(
        BlueprintQuery $query
    ): BlueprintPage;
}
