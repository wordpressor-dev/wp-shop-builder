<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Release\ReleaseRowMapper;
use WPShop\App\Plugin\Release\WordPressReleaseRepository;
use WPShop\Release\Exception\ReleasePersistenceFailed;
use WPShop\Release\ReleaseQuery;

final class WordPressReleaseRepositoryPaginationTest extends TestCase
{
    public function testReturnsReleasePageAndTotal(): void
    {
        $database = new ReleasePaginationDatabase();
        $database->rows = [$this->row()];
        $database->total = 101;

        $query = new ReleaseQuery(
            blueprintId: 7,
            status: 'published',
            published: true,
            sortBy: ReleaseQuery::SORT_CREATED_AT,
            sortDirection:
                ReleaseQuery::DIRECTION_ASCENDING,
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
            'ORDER BY created_at ASC',
            $database->collectionSql
        );

        self::assertStringContainsString(
            'LIMIT %d OFFSET %d',
            $database->collectionSql
        );

        self::assertSame(
            [
                7,
                'published',
                1,
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
            'blueprint_id = %d',
            $database->countSql
        );

        self::assertStringContainsString(
            'status = %s',
            $database->countSql
        );

        self::assertStringContainsString(
            'published = %d',
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
                7,
                'published',
                1,
            ],
            $database->countParameters
        );
    }

    public function testReturnsEmptyPageBeyondTotal(): void
    {
        $database = new ReleasePaginationDatabase();
        $database->total = 3;

        $query = new ReleaseQuery(
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
        $database = new ReleasePaginationDatabase();

        $database->countException =
            new RuntimeException(
                'Native count query failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release collection lookup failed.'
        );

        $this->repository($database)
            ->findPage(new ReleaseQuery());
    }

    private function repository(
        ReleasePaginationDatabase $database
    ): WordPressReleaseRepository {
        return new WordPressReleaseRepository(
            $database,
            new ReleaseRowMapper(),
            'wp_wps_releases',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 12:00:00'
                )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => '42',
            'blueprint_id' => '7',
            'version' => '1.2.3',
            'status' => 'published',
            'manifest_id' => '15',
            'published' => '1',
            'validation_score' => '98.75',
            'created_at' => '2026-08-01 12:00:00',
        ];
    }
}

final class ReleasePaginationDatabase implements
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
