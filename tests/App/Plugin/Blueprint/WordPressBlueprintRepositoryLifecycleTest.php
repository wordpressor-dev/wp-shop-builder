<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final class WordPressBlueprintRepositoryLifecycleTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testUpdatesAndReloadsBlueprint(): void
    {
        $database = new LifecycleDatabase();
        $database->updateResult = 1;
        $database->row = $this->updatedRow();

        $blueprint = $this->repository($database)
            ->update(42, $this->updateData());

        self::assertNotNull($blueprint);

        self::assertSame(
            'updated-plugin',
            $blueprint->slug()
        );

        self::assertSame(
            'published',
            $blueprint->state()
        );

        self::assertSame(
            [
                'id' => 42,
                'deleted_at' => null,
            ],
            $database->updateWhere
        );

        self::assertSame(
            [42],
            $database->fetchParameters
        );
    }

    public function testReturnsNullWhenUpdatedBlueprintIsMissing(): void
    {
        $database = new LifecycleDatabase();
        $database->updateResult = 0;
        $database->row = null;

        self::assertNull(
            $this->repository($database)
                ->update(42, $this->updateData())
        );
    }

    public function testSoftDeletesBlueprint(): void
    {
        $database = new LifecycleDatabase();
        $database->updateResult = 1;

        $result = $this->repository($database)
            ->softDelete(42);

        self::assertTrue($result);

        self::assertSame(
            [
                'deleted_at' =>
                    '2026-07-31 20:00:00',
                'updated_at' =>
                    '2026-07-31 20:00:00',
            ],
            $database->updateData
        );

        self::assertSame(
            [
                'id' => 42,
                'deleted_at' => null,
            ],
            $database->updateWhere
        );
    }

    public function testReturnsFalseWhenDeleteTargetIsMissing(): void
    {
        $database = new LifecycleDatabase();
        $database->updateResult = 0;

        self::assertFalse(
            $this->repository($database)
                ->softDelete(42)
        );
    }

    public function testWrapsUpdateFailure(): void
    {
        $database = new LifecycleDatabase();

        $database->updateException =
            new RuntimeException(
                'Native update failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint 42 update failed.'
        );

        $this->repository($database)
            ->update(42, $this->updateData());
    }

    private function repository(
        LifecycleDatabase $database
    ): WordPressBlueprintRepository {
        return new WordPressBlueprintRepository(
            $database,
            new BlueprintRowMapper(),
            'wp_wps_blueprints',
            static fn (): string => self::UUID,
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-07-31 20:00:00'
                )
        );
    }

    private function updateData(): BlueprintUpdateData
    {
        return new BlueprintUpdateData(
            'updated-plugin',
            'plugin',
            null,
            null,
            11,
            'published',
            'reviewed'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function updatedRow(): array
    {
        return [
            'id' => '42',
            'uuid' => self::UUID,
            'slug' => 'updated-plugin',
            'type' => 'plugin',
            'provider_id' => null,
            'developer_id' => null,
            'current_release_id' => '11',
            'state' => 'published',
            'workflow' => 'reviewed',
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 20:00:00',
            'deleted_at' => null,
        ];
    }
}

final class LifecycleDatabase implements
    DatabaseConnectionInterface
{
    public int $updateResult = 0;

    public ?RuntimeException $updateException = null;

    /**
     * @var array<string, int|float|string|null>
     */
    public array $updateData = [];

    /**
     * @var array<string, int|float|string|null>
     */
    public array $updateWhere = [];

    /**
     * @var list<int|float|string>
     */
    public array $fetchParameters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $row = null;

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        return 1;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        if ($this->updateException !== null) {
            throw $this->updateException;
        }

        $this->updateData = $data;
        $this->updateWhere = $where;

        return $this->updateResult;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        $this->fetchParameters = $parameters;

        return $this->row;
    }
}
