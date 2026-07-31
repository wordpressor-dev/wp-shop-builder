<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
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

    public function getById(int $id): Blueprint;

    public function getByUuid(string $uuid): Blueprint;
}
