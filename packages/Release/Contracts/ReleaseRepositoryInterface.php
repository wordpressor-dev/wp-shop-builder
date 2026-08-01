<?php

declare(strict_types=1);

namespace WPShop\Release\Contracts;

use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;

interface ReleaseRepositoryInterface
{
    public function create(
        ReleaseCreateData $data
    ): Release;

    public function findById(int $id): ?Release;

    public function findByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): ?Release;
}
