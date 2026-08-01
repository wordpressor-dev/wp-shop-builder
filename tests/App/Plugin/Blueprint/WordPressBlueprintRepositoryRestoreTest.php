<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final class WordPressBlueprintRepositoryRestoreTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testFindsDeletedBlueprintByIdentifier(): void
    {
        $database = new RestoreDatabase();
        $database->rows = [$this->deletedRow()];

        $blueprint = $this->repository($database)
            ->findByIdIncludingDeleted(42);

        self::assertNotNull($blueprint);

        self::assertSame(
            '2026-07-31 18:00:00',
            $blueprint->deletedAt()?->format(
                'Y-m-d H:i:s'
            )
        );

        self::assertSame(
            [42],
            $database->fetchParameters[0]
        );

        self::assertStringNotContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql[0]
        );
    }

    public function testFindsDeletedBlueprintByUuid(): void
    {
        $database = new RestoreDatabase();
        $database->rows = [$this->deletedRow()];

        $blueprint = $this->repository($database)
            ->findByUuidIncludingDeleted(self::UUID);

        self::assertNotNull($blueprint);

        self::assertSame(
            self::UUID,
            $blueprint->uuid()
        );

        self::assertSame(
            [self::UUID],
            $database->fetchParameters[0]
        );

        self::assertStringNotContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql[0]
        );
    }

    public function testRestoresDeletedBlueprint(): void
    {
        $database = new RestoreDatabase();
        $database->updateResult = 1;

        $database->rows = [
            $this->deletedRow(),
            $this->activeRow(),
        ];

        $blueprint = $this->repository($database)
            ->restore(42);

        self::assertNotNull($blueprint);
        self::assertNull($blueprint->deletedAt());
        self::assertSame(1, $database->updateCalls);

        self::assertSame(
            [
                'deleted_at' => null,
                'updated_at' => '2026-07-31 20:00:00',
            ],
            $database->updateData
        );

        self::assertSame(
            [
                'id' => 42,
            ],
            $database->updateWhere
        );

        self::assertStringNotContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql[0]
        );

        self::assertStringContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql[1]
        );
    }

    public function testReturnsActiveBlueprintWithoutUpdate(): void
    {
        $database = new RestoreDatabase();
        $database->rows = [$this->activeRow()];

        $blueprint = $this->repository($database)
            ->restore(42);

        self::assertNotNull($blueprint);
        self::assertNull($blueprint->deletedAt());
        self::assertSame(0, $database->updateCalls);
    }

    public function testReturnsNullWhenRestoreTargetIsMissing(): void
    {
        $database = new RestoreDatabase();
        $database->rows = [null];

        self::assertNull(
            $this->repository($database)
                ->restore(42)
        );

        self::assertSame(0, $database->updateCalls);
    }

    public function testWrapsRestoreFailure(): void
    {
        $database = new RestoreDatabase();
        $database->rows = [$this->deletedRow()];

        $database->updateException =
            new RuntimeException(
                'Native restore failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint 42 restoration failed.'
        );

        $this->repository($database)->restore(42);
    }

    private function repository(
        RestoreDatabase $database
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

    /**
     * @return array<string, mixed>
     */
    private function deletedRow(): array
    {
        $row = $this->activeRow();
        $row['deleted_at'] = '2026-07-31 18:00:00';

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRow(): array
    {
        return [
            'id' => '42',
            'uuid' => self::UUID,
            'slug' => 'example-plugin',
            'type' => 'plugin',
            'provider_id' => null,
            'developer_id' => null,
            'current_release_id' => null,
            'state' => 'draft',
            'workflow' => 'default',
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 20:00:00',
            'deleted_at' => null,
        ];
    }
}

final class RestoreDatabase implements
    DatabaseConnectionInterface
{
    public int $updateResult = 0;

    public int $updateCalls = 0;

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
     * @var list<string>
     */
    public array $fetchSql = [];

    /**
     * @var list<list<int|float|string>>
     */
    public array $fetchParameters = [];

    /**
     * @var list<array<string, mixed>|null>
     */
    public array $rows = [];

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
        ++$this->updateCalls;

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
        $this->fetchSql[] = $sql;
        $this->fetchParameters[] = $parameters;

        if ($this->rows === []) {
            return null;
        }

        return array_shift($this->rows);
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        return [];
    }
}
