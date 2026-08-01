<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;
use WPShop\Release\ReleaseUpdateData;

interface ReleaseServiceInterface
{
    public function create(
        ReleaseCreateData $data
    ): Release;

    public function update(
        int $id,
        ReleaseUpdateData $data
    ): Release;

    public function getById(int $id): Release;

    public function getByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): Release;
}
