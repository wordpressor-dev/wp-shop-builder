<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Manifest;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Manifest\ManifestRowMapper;
use WPShop\App\Plugin\Manifest\WordPressManifestRepository;
use WPShop\Manifest\Exception\ManifestPersistenceFailed;
use WPShop\Manifest\ManifestQuery;

final class WordPressManifestRepositoryPaginationTest extends TestCase
{
    private const string MANIFEST_JSON =
        '{"name":"example-plugin"}';

    public function testReturnsManifestPageAndTotal(): void
    {
        $manifestHash = hash(
            'sha256',
            self::MANIFEST_JSON
        );

        $database = new ManifestPaginationDatabase();
        $database->rows = [$this->row()];
        $database->total = 101;

        $query = new ManifestQuery(
            releaseId: 7,
            manifestHash: $manifestHash,
            sortBy: ManifestQuery::SORT_CREATED_AT,
            sortDirection:
                ManifestQuery::DIRECTION_ASCENDING,
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
                $manifestHash,
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
            'release_id = %d',
            $database->countSql
        );

        self::assertStringContainsString(
            'manifest_hash = %s',
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
                $manifestHash,
            ],
            $database->countParameters
        );
    }

    public function testReturnsEmptyPageBeyondTotal(): void
    {
        $database = new ManifestPaginationDatabase();
        $database->total = 3;

        $query = new ManifestQuery(
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
        $database = new ManifestPaginationDatabase();

        $database->countException =
            new RuntimeException(
                'Native count query failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest collection lookup failed.'
        );

        $this->repository($database)
            ->findPage(new ManifestQuery());
    }

    private function repository(
        ManifestPaginationDatabase $database
    ): WordPressManifestRepository {
        return new WordPressManifestRepository(
            $database,
            new ManifestRowMapper(),
            'wp_wps_manifests',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-02 13:00:00'
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
            'release_id' => '7',
            'manifest_json' => self::MANIFEST_JSON,
            'manifest_hash' => hash(
                'sha256',
                self::MANIFEST_JSON
            ),
            'created_at' =>
                '2026-08-02 13:00:00',
        ];
    }
}

final class ManifestPaginationDatabase implements
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
        if ($this->countException instanceof \RuntimeException) {
            throw $this->countException;
        }

        $this->countSql = $sql;
        $this->countParameters = $parameters;

        return $this->total;
    }
}
