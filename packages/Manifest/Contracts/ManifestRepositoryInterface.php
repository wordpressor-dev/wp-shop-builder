<?php

declare(strict_types=1);

namespace WPShop\Manifest\Contracts;

use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;

interface ManifestRepositoryInterface
{
    public function create(
        ManifestCreateData $data
    ): Manifest;

    public function findById(
        int $id
    ): ?Manifest;

    public function findByReleaseId(
        int $releaseId
    ): ?Manifest;
}
