<?php

declare(strict_types=1);

namespace WPShop\Manifest\Contracts;

use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;

interface ManifestServiceInterface
{
    public function create(
        ManifestCreateData $data
    ): Manifest;

    public function getById(int $id): Manifest;

    public function getByReleaseId(
        int $releaseId
    ): Manifest;
}
