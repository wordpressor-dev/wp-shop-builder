<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;

interface BlueprintRepositoryInterface
{
    public function create(
        BlueprintCreateData $data
    ): Blueprint;

    public function findById(int $id): ?Blueprint;

    public function findByUuid(string $uuid): ?Blueprint;
}
