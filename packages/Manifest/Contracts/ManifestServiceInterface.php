<?php

declare(strict_types=1);

namespace WPShop\Manifest\Contracts;

use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestPage;
use WPShop\Manifest\ManifestQuery;
use WPShop\Manifest\ManifestUpdateData;

interface ManifestServiceInterface
{
    public function create(
        ManifestCreateData $data
    ): Manifest;

    public function update(
        int $id,
        ManifestUpdateData $data
    ): Manifest;

    public function getById(int $id): Manifest;

    public function getByReleaseId(
        int $releaseId
    ): Manifest;

    /**
     * @return list<Manifest>
     */
    public function getAll(
        ManifestQuery $query
    ): array;

    public function getPage(
        ManifestQuery $query
    ): ManifestPage;
}
