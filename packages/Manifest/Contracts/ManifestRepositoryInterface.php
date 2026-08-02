<?php

declare(strict_types=1);

namespace WPShop\Manifest\Contracts;

use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestUpdateData;

interface ManifestRepositoryInterface
{
    public function create(
        ManifestCreateData $data
    ): Manifest;

    public function update(
        int $id,
        ManifestUpdateData $data
    ): ?Manifest;

    public function findById(
        int $id
    ): ?Manifest;

    public function findByReleaseId(
        int $releaseId
    ): ?Manifest;
}
