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

final class WordPressReleaseRepositoryCollectionTest extends TestCase
{
    public function testFindsDefaultReleaseCollection(): void
    {
        $database = new ReleaseCollectionDatabase();

        $database->rows = [
            $this->row(
                43,
                7,
                '1.2.4'
            ),
            $this->row(
                42,
                7,
                '1.2.3'
            ),
        ];

        $releases = $this->repository($database)
            ->findAll(new ReleaseQuery());

        self::assertCount(2, $releases);
        self::assertSame(43, $releases[0]->id());
        self::assertSame(42, $releases[1]->id());

        self::assertStringContainsString(
            'WHERE 1 = 1',
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
        $database = new ReleaseCollectionDatabase();

        $database->rows = [
            $this->row(
                42,
                7,
                '1.2.3',
                'published',
                '1',
                '98.75'
            ),
        ];

        $query = new ReleaseQuery(
            blueprintId: 7,
            status: 'published',
            published: true,
            sortBy:
                ReleaseQuery::SORT_VALIDATION_SCORE,
            sortDirection:
                ReleaseQuery::DIRECTION_ASCENDING,
            limit: 25,
            offset: 50
        );

        $releases = $this->repository($database)
            ->findAll($query);

        self::assertCount(1, $releases);

        self::assertSame(
            '1.2.3',
            $releases[0]->version()
        );

        self::assertStringContainsString(
            'blueprint_id = %d',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'status = %s',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'published = %d',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'ORDER BY validation_score ASC',
            $database->fetchSql
        );

        self::assertSame(
            [
                7,
                'published',
                1,
                25,
                50,
            ],
            $database->fetchParameters
        );
    }

    public function testAppliesUnpublishedFilter(): void
    {
        $database = new ReleaseCollectionDatabase();

        $query = new ReleaseQuery(
            published: false
        );

        $this->repository($database)->findAll($query);

        self::assertStringContainsString(
            'published = %d',
            $database->fetchSql
        );

        self::assertSame(
            [
                0,
                50,
                0,
            ],
            $database->fetchParameters
        );
    }

    public function testReturnsEmptyReleaseCollection(): void
    {
        $database = new ReleaseCollectionDatabase();

        $releases = $this->repository($database)
            ->findAll(new ReleaseQuery());

        self::assertSame([], $releases);
    }

    public function testWrapsCollectionFailure(): void
    {
        $database = new ReleaseCollectionDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native collection lookup failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release collection lookup failed.'
        );

        $this->repository($database)
            ->findAll(new ReleaseQuery());
    }

    private function repository(
        ReleaseCollectionDatabase $database
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
    private function row(
        int $id,
        int $blueprintId,
        string $version,
        string $status = 'draft',
        string $published = '0',
        ?string $validationScore = null,
        string $createdAt = '2026-08-01 12:00:00'
    ): array {
        return [
            'id' => (string) $id,
            'blueprint_id' => (string) $blueprintId,
            'version' => $version,
            'status' => $status,
            'manifest_id' => null,
            'published' => $published,
            'validation_score' => $validationScore,
            'created_at' => $createdAt,
        ];
    }
}

final class ReleaseCollectionDatabase implements
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

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
