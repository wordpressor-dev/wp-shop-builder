<?php

declare(strict_types=1);

namespace WPShop\Tests\Release;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Release\Exception\ReleasePublicationNotAllowed;
use WPShop\Release\Policy\DefaultReleasePublicationPolicy;
use WPShop\Release\Release;

final class DefaultReleasePublicationPolicyTest extends TestCase
{
    public function testAllowsEligibleRelease(): void
    {
        $policy = new DefaultReleasePublicationPolicy();

        $policy->assertCanPublish(
            $this->blueprint(),
            $this->release()
        );

        $this->addToAssertionCount(1);
    }

    public function testRejectsDeletedBlueprint(): void
    {
        $policy = new DefaultReleasePublicationPolicy();

        $this->expectException(
            ReleasePublicationNotAllowed::class
        );

        $this->expectExceptionMessage(
            'Release 10 cannot be published because Blueprint 5 is deleted.'
        );

        $policy->assertCanPublish(
            $this->blueprint(
                deletedAt: new DateTimeImmutable(
                    '2026-08-03 12:00:00'
                )
            ),
            $this->release()
        );
    }

    public function testRejectsAlreadyPublishedRelease(): void
    {
        $policy = new DefaultReleasePublicationPolicy();

        $this->expectException(
            ReleasePublicationNotAllowed::class
        );

        $this->expectExceptionMessage(
            'Release 10 is already published.'
        );

        $policy->assertCanPublish(
            $this->blueprint(),
            $this->release(
                published: true
            )
        );
    }

    public function testRejectsBlueprintMismatch(): void
    {
        $policy = new DefaultReleasePublicationPolicy();

        $this->expectException(
            ReleasePublicationNotAllowed::class
        );

        $this->expectExceptionMessage(
            'Release 10 belongs to Blueprint 6, not Blueprint 5.'
        );

        $policy->assertCanPublish(
            $this->blueprint(),
            $this->release(
                blueprintId: 6
            )
        );
    }

    private function blueprint(
        int $id = 5,
        ?DateTimeImmutable $deletedAt = null
    ): Blueprint {
        return new Blueprint(
            $id,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-blueprint',
            'plugin',
            null,
            null,
            null,
            'active',
            'draft',
            new DateTimeImmutable(
                '2026-08-01 10:00:00'
            ),
            new DateTimeImmutable(
                '2026-08-02 10:00:00'
            ),
            $deletedAt
        );
    }

    private function release(
        int $blueprintId = 5,
        bool $published = false
    ): Release {
        return new Release(
            10,
            $blueprintId,
            '1.0.0',
            $published ? 'published' : 'draft',
            $published ? 20 : null,
            $published,
            $published ? 98.75 : null,
            new DateTimeImmutable(
                '2026-08-03 10:00:00'
            )
        );
    }
}
