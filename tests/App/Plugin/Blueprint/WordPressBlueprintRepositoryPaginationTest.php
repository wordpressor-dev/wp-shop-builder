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

final class WordPressBlueprintRepositoryPaginationTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testReturnsBlueprintPageAndTotal(): void
    {
        $database = new PaginationDatabase();
        $database->rows = [$this->row()];
        $database->total = 101;

        $query = new BlueprintQuery(
            type: 'plugin',
            state: 'published',
            workflow: 'reviewed',
            sortBy: BlueprintQuery::SORT_UPDATED_AT,
            sortDirection:
                BlueprintQuery::DIRECTION_ASCENDING,
            limit: 25,
            offset: 50
        );

        $page = $this->repository($database)
            ->findPage($query);

        self::assertCount(1, $page->items());
        self::assertSame(101, $page->total());
        self::assertSame(25, $page->limit());
        self::assertSame(50, $page->offset());
        self::assertSame(5, $page->totalPages());

        self::assertStringContainsString(
            'ORDER BY updated_at ASC',
            $database->collectionSql
        );

        self::assertStringContainsString(
            'LIMIT %d OFFSET %d',
            $database->collectionSql
        );

        self::assertSame(
            [
                'plugin',
                'published',
                'reviewed',
                25,
                50,
            ],
            $database->collectionParameters
        );

        self::assertStringContainsString(
            'SELECT COUNT(*)',
            $database->countSql
        );

        self::assertStringContainsString(
            'deleted_at IS NULL',
            $database->countSql
        );

        self::assertStringNotContainsString(
            'ORDER BY',
            $database->countSql
        );

        self::assertStringNotContainsString(
            'LIMIT',
            $database->countSql
        );

        self::assertSame(
            [
                'plugin',
                'published',
                'reviewed',
            ],
            $database->countParameters
        );
    }

    public function testIncludesDeletedBlueprintsInPageTotal(): void
    {
        $database = new PaginationDatabase();
        $database->rows = [
            $this->row(
                '2026-08-01 08:00:00'
            ),
        ];
        $database->total = 2;

        $query = new BlueprintQuery(
            includingDeleted: true
        );

        $page = $this->repository($database)
            ->findPage($query);

        self::assertSame(2, $page->total());

        self::assertNotNull(
            $page->items()[0]->deletedAt()
        );

        self::assertStringNotContainsString(
            'deleted_at IS NULL',
            $database->collectionSql
        );

        self::assertStringNotContainsString(
            'deleted_at IS NULL',
            $database->countSql
        );
    }

    public function testReturnsEmptyPageBeyondTotal(): void
    {
        $database = new PaginationDatabase();
        $database->total = 3;

        $query = new BlueprintQuery(
            limit: 25,
            offset: 50
        );

        $page = $this->repository($database)
            ->findPage($query);

        self::assertSame([], $page->items());
        self::assertSame(3, $page->total());
        self::assertSame(1, $page->totalPages());
        self::assertSame(50, $page->offset());
    }

    public function testWrapsPaginationTotalFailure(): void
    {
        $database = new PaginationDatabase();

        $database->countException =
            new RuntimeException(
                'Native count query failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint collection lookup failed.'
        );

        $this->repository($database)
            ->findPage(new BlueprintQuery());
    }

    private function repository(
        PaginationDatabase $database
    ): WordPressBlueprintRepository {
        return new WordPressBlueprintRepository(
            $database,
            new BlueprintRowMapper(),
            'wp_wps_blueprints',
            static fn (): string => self::UUID,
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 08:00:00'
                )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        ?string $deletedAt = null
    ): array {
        return [
            'id' => '42',
            'uuid' => self::UUID,
            'slug' => 'example-plugin',
            'type' => 'plugin',
            'provider_id' => null,
            'developer_id' => null,
            'current_release_id' => null,
            'state' => 'published',
            'workflow' => 'reviewed',
            'created_at' => '2026-08-01 07:00:00',
            'updated_at' => '2026-08-01 08:00:00',
            'deleted_at' => $deletedAt,
        ];
    }
}

final class PaginationDatabase implements
    DatabaseConnectionInterface
{
    public string $collectionSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $collectionParameters = [];

    public string $countSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $countParameters = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $rows = [];

    public int $total = 0;

    public ?RuntimeException $countException = null;

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
        $this->collectionSql = $sql;
        $this->collectionParameters = $parameters;

        return $this->rows;
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        if ($this->countException !== null) {
            throw $this->countException;
        }

        $this->countSql = $sql;
        $this->countParameters = $parameters;

        return $this->total;
    }
}
