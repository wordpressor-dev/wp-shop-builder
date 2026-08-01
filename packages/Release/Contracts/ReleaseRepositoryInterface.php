<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;
use WPShop\Release\ReleaseUpdateData;

interface ReleaseRepositoryInterface
{
    public function create(
        ReleaseCreateData $data
    ): Release;

    public function update(
        int $id,
        ReleaseUpdateData $data
    ): ?Release;

    public function findById(int $id): ?Release;

    public function findByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): ?Release;
}
