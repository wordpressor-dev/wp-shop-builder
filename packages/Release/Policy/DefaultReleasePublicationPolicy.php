<?php

declare(strict_types=1);

namespace WPShop\Release\Policy;

use WPShop\Blueprint\Blueprint;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Exception\ReleasePublicationNotAllowed;
use WPShop\Release\Release;

final class DefaultReleasePublicationPolicy implements
    ReleasePublicationPolicyInterface
{
    public function assertCanPublish(
        Blueprint $blueprint,
        Release $release
    ): void {
        if ($release->blueprintId() !== $blueprint->id()) {
            throw ReleasePublicationNotAllowed::blueprintMismatch(
                $release->id(),
                $release->blueprintId(),
                $blueprint->id()
            );
        }

        if ($blueprint->deletedAt() instanceof \DateTimeImmutable) {
            throw ReleasePublicationNotAllowed::deletedBlueprint(
                $release->id(),
                $blueprint->id()
            );
        }

        if ($release->published()) {
            throw ReleasePublicationNotAllowed::alreadyPublished(
                $release->id()
            );
        }
    }
}
