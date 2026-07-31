<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\BlueprintCreateData;

final class BlueprintCreateDataTest extends TestCase
{
    public function testUsesDefaultCreationState(): void
    {
        $data = new BlueprintCreateData(
            'example-theme',
            'theme'
        );

        self::assertSame(
            'example-theme',
            $data->slug()
        );

        self::assertSame('theme', $data->type());
        self::assertNull($data->providerId());
        self::assertNull($data->developerId());
        self::assertSame('draft', $data->state());
        self::assertSame('default', $data->workflow());
    }

    public function testRejectsInvalidOptionalIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint providerId must be a positive integer.'
        );

        new BlueprintCreateData(
            'example',
            'plugin',
            0
        );
    }
}
