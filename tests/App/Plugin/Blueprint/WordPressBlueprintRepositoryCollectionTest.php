<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\BlueprintQuery;
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final class WordPressBlueprintRepositoryCollectionTest extends TestCase
{
    private const FIRST_UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    private const SECOND_UUID =
        '123e4567-e89b-12d3-a456-426614174001';

    public function testFindsDefaultBlueprintCollection(): void
    {
        $database = new CollectionDatabase();

        $database->rows = [
            $this->row(
                43,
                self::SECOND_UUID,
                'second-plugin'
            ),
            $this->row(
                42,
                self::FIRST_UUID,
                'first-plugin'
            ),
        ];

        $blueprints = $this->repository($database)
            ->findAll(new BlueprintQuery());

        self::assertCount(2, $blueprints);
        self::assertSame(43, $blueprints[0]->id());
        self::assertSame(42, $blueprints[1]->id());

        self::assertStringContainsString(
            'WHERE 1 = 1 AND deleted_at IS NULL',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'ORDER BY id DESC',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'LIMIT %d OFFSET %d',
            $database->fetchSql
        );

        self::assertSame(
            [50, 0],
            $database->fetchParameters
        );
    }

    public function testAppliesFiltersAndPagination(): void
    {
        $database = new CollectionDatabase();

        $database->rows = [
            $this->row(
                42,
                self::FIRST_UUID,
                'example-plugin',
                'published',
                'reviewed',
                '2026-07-31 21:00:00'
            ),
        ];

        $query = new BlueprintQuery(
            type: 'plugin',
            state: 'published',
            workflow: 'reviewed',
            includingDeleted: true,
            sortBy: BlueprintQuery::SORT_UPDATED_AT,
            sortDirection:
                BlueprintQuery::DIRECTION_ASCENDING,
            limit: 25,
            offset: 50
        );

        $blueprints = $this->repository($database)
            ->findAll($query);

        self::assertCount(1, $blueprints);

        self::assertSame(
            'example-plugin',
            $blueprints[0]->slug()
        );

        self::assertStringContainsString(
            'type = %s',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'state = %s',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'workflow = %s',
            $database->fetchSql
        );

        self::assertStringNotContainsString(
            'deleted_at IS NULL',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'ORDER BY updated_at ASC',
            $database->fetchSql
        );

        self::assertSame(
            [
                'plugin',
                'published',
                'reviewed',
                25,
                50,
            ],
            $database->fetchParameters
        );
    }

    public function testMapsDeletedBlueprintWhenIncluded(): void
    {
        $database = new CollectionDatabase();

        $database->rows = [
            $this->row(
                42,
                self::FIRST_UUID,
                'deleted-plugin',
                deletedAt: '2026-07-31 22:00:00'
            ),
        ];

        $query = new BlueprintQuery(
            includingDeleted: true
        );

        $blueprints = $this->repository($database)
            ->findAll($query);

        self::assertCount(1, $blueprints);

        self::assertSame(
            '2026-07-31 22:00:00',
            $blueprints[0]->deletedAt()?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function testReturnsEmptyBlueprintCollection(): void
    {
        $database = new CollectionDatabase();

        $blueprints = $this->repository($database)
            ->findAll(new BlueprintQuery());

        self::assertSame([], $blueprints);
    }

    public function testWrapsCollectionFailure(): void
    {
        $database = new CollectionDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native collection lookup failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint collection lookup failed.'
        );

        $this->repository($database)
            ->findAll(new BlueprintQuery());
    }

    private function repository(
        CollectionDatabase $database
    ): WordPressBlueprintRepository {
        return new WordPressBlueprintRepository(
            $database,
            new BlueprintRowMapper(),
            'wp_wps_blueprints',
            static fn (): string => self::FIRST_UUID,
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-07-31 20:00:00'
                )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        int $id,
        string $uuid,
        string $slug,
        string $state = 'draft',
        string $workflow = 'default',
        string $updatedAt = '2026-07-31 20:00:00',
        ?string $deletedAt = null
    ): array {
        return [
            'id' => (string) $id,
            'uuid' => $uuid,
            'slug' => $slug,
            'type' => 'plugin',
            'provider_id' => null,
            'developer_id' => null,
            'current_release_id' => null,
            'state' => $state,
            'workflow' => $workflow,
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => $updatedAt,
            'deleted_at' => $deletedAt,
        ];
    }
}

final class CollectionDatabase implements
    DatabaseConnectionInterface
{
    public string $fetchSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $fetchParameters = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    public ?RuntimeException $fetchException = null;

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
        return 0;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        return null;
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        if ($this->fetchException !== null) {
            throw $this->fetchException;
        }

        $this->fetchSql = $sql;
        $this->fetchParameters = $parameters;

        return $this->rows;
    }
}
