<?php

declare(strict_types=1);

namespace WPShop\Blueprint\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;

interface BlueprintServiceInterface
{
    public function create(
        BlueprintCreateData $data
    ): Blueprint;

    public function getById(int $id): Blueprint;

    public function getByUuid(string $uuid): Blueprint;
}
