<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\StoredArtifact;
use WPShop\Release\Release;

interface ArtifactStorageInterface
{
    public function store(
        Blueprint $blueprint,
        Release $release,
        PublicationArtifact $artifact
    ): StoredArtifact;

    public function delete(StoredArtifact $artifact): void;
}
