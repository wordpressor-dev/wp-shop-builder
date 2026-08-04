<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\StoredArtifact;

interface ArtifactManifestDecoratorInterface
{
    public function decorate(
        string $manifestJson,
        StoredArtifact $artifact
    ): string;
}
