<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\BlueprintUpdateData;

final class BlueprintUpdateDataTest extends TestCase
{
    public function testExposesUpdatedBlueprintData(): void
    {
        $data = new BlueprintUpdateData(
            'updated-plugin',
            'plugin',
            7,
            9,
            11,
            'published',
            'reviewed'
        );

        self::assertSame(
            'updated-plugin',
            $data->slug()
        );

        self::assertSame('plugin', $data->type());
        self::assertSame(7, $data->providerId());
        self::assertSame(9, $data->developerId());

        self::assertSame(
            11,
            $data->currentReleaseId()
        );

        self::assertSame(
            'published',
            $data->state()
        );

        self::assertSame(
            'reviewed',
            $data->workflow()
        );
    }

    public function testAllowsNullableRelations(): void
    {
        $data = new BlueprintUpdateData(
            'updated-theme',
            'theme',
            null,
            null,
            null,
            'draft',
            'default'
        );

        self::assertNull($data->providerId());
        self::assertNull($data->developerId());
        self::assertNull($data->currentReleaseId());
    }

    public function testRejectsInvalidReleaseIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint currentReleaseId must be a positive integer.'
        );

        new BlueprintUpdateData(
            'updated-plugin',
            'plugin',
            null,
            null,
            0,
            'draft',
            'default'
        );
    }
}
