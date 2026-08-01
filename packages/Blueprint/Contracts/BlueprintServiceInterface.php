<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\BlueprintPage;
use WPShop\Blueprint\BlueprintQuery;
use WPShop\Blueprint\BlueprintUpdateData;

interface BlueprintServiceInterface
{
    public function create(
        BlueprintCreateData $data
    ): Blueprint;

    public function update(
        int $id,
        BlueprintUpdateData $data
    ): Blueprint;

    public function delete(int $id): void;

    public function restore(int $id): Blueprint;

    public function getById(int $id): Blueprint;

    public function getByUuid(string $uuid): Blueprint;

    public function getBySlug(string $slug): Blueprint;

    public function getByIdIncludingDeleted(
        int $id
    ): Blueprint;

    public function getByUuidIncludingDeleted(
        string $uuid
    ): Blueprint;

    public function getBySlugIncludingDeleted(
        string $slug
    ): Blueprint;

    /**
     * @return list<Blueprint>
     */
    public function getAll(
        BlueprintQuery $query
    ): array;

    public function getPage(
        BlueprintQuery $query
    ): BlueprintPage;
}
