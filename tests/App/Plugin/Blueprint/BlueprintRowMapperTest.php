<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;

final class BlueprintRowMapperTest extends TestCase
{
    public function testMapsDatabaseRowToBlueprint(): void
    {
        $mapper = new BlueprintRowMapper();

        $blueprint = $mapper->map(
            $this->validRow()
        );

        self::assertSame(42, $blueprint->id());

        self::assertSame(
            '123e4567-e89b-12d3-a456-426614174000',
            $blueprint->uuid()
        );

        self::assertSame(
            'example-plugin',
            $blueprint->slug()
        );

        self::assertSame(
            'plugin',
            $blueprint->type()
        );

        self::assertSame(7, $blueprint->providerId());
        self::assertSame(9, $blueprint->developerId());

        self::assertSame(
            11,
            $blueprint->currentReleaseId()
        );

        self::assertSame(
            '2026-07-31 10:00:00',
            $blueprint->createdAt()->format(
                'Y-m-d H:i:s'
            )
        );

        self::assertNull($blueprint->deletedAt());
    }

    public function testRejectsMissingDatabaseField(): void
    {
        $row = $this->validRow();

        unset($row['uuid']);

        $mapper = new BlueprintRowMapper();

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Blueprint database field "uuid" is invalid.'
        );

        $mapper->map($row);
    }

    public function testRejectsInvalidDatabaseDate(): void
    {
        $row = $this->validRow();
        $row['created_at'] = '2026-02-31 10:00:00';

        $mapper = new BlueprintRowMapper();

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Blueprint database field "created_at" is invalid.'
        );

        $mapper->map($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRow(): array
    {
        return [
            'id' => '42',
            'uuid' =>
                '123e4567-e89b-12d3-a456-426614174000',
            'slug' => 'example-plugin',
            'type' => 'plugin',
            'provider_id' => '7',
            'developer_id' => '9',
            'current_release_id' => '11',
            'state' => 'draft',
            'workflow' => 'default',
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 11:00:00',
            'deleted_at' => null,
        ];
    }
}
