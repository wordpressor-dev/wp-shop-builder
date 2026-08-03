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

final class WordPressManifestRepositoryCollectionTest extends TestCase
{
    private const string MANIFEST_JSON =
        '{"name":"example-plugin"}';

    public function testFindsDefaultManifestCollection(): void
    {
        $database = new ManifestCollectionDatabase();

        $database->rows = [
            $this->row(
                43,
                8,
                '{"name":"second-plugin"}'
            ),
            $this->row(
                42,
                7,
                self::MANIFEST_JSON
            ),
        ];

        $manifests = $this->repository($database)
            ->findAll(new ManifestQuery());

        self::assertCount(2, $manifests);
        self::assertSame(43, $manifests[0]->id());
        self::assertSame(42, $manifests[1]->id());

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
        $manifestHash = hash(
            'sha256',
            self::MANIFEST_JSON
        );

        $database = new ManifestCollectionDatabase();

        $database->rows = [
            $this->row(
                42,
                7,
                self::MANIFEST_JSON
            ),
        ];

        $query = new ManifestQuery(
            releaseId: 7,
            manifestHash: $manifestHash,
            sortBy:
                ManifestQuery::SORT_MANIFEST_HASH,
            sortDirection:
                ManifestQuery::DIRECTION_ASCENDING,
            limit: 25,
            offset: 50
        );

        $manifests = $this->repository($database)
            ->findAll($query);

        self::assertCount(1, $manifests);

        self::assertSame(
            self::MANIFEST_JSON,
            $manifests[0]->manifestJson()
        );

        self::assertStringContainsString(
            'release_id = %d',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'manifest_hash = %s',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'ORDER BY manifest_hash ASC',
            $database->fetchSql
        );

        self::assertSame(
            [
                7,
                $manifestHash,
                25,
                50,
            ],
            $database->fetchParameters
        );
    }

    public function testMapsReleaseIdentifierSortColumn(): void
    {
        $database = new ManifestCollectionDatabase();

        $query = new ManifestQuery(
            sortBy: ManifestQuery::SORT_RELEASE_ID,
            sortDirection:
                ManifestQuery::DIRECTION_ASCENDING
        );

        $this->repository($database)->findAll($query);

        self::assertStringContainsString(
            'ORDER BY release_id ASC',
            $database->fetchSql
        );
    }

    public function testReturnsEmptyManifestCollection(): void
    {
        $database = new ManifestCollectionDatabase();

        $manifests = $this->repository($database)
            ->findAll(new ManifestQuery());

        self::assertSame([], $manifests);
    }

    public function testWrapsCollectionFailure(): void
    {
        $database = new ManifestCollectionDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native collection lookup failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest collection lookup failed.'
        );

        $this->repository($database)
            ->findAll(new ManifestQuery());
    }

    private function repository(
        ManifestCollectionDatabase $database
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
    private function row(
        int $id,
        int $releaseId,
        string $manifestJson
    ): array {
        return [
            'id' => (string) $id,
            'release_id' => (string) $releaseId,
            'manifest_json' => $manifestJson,
            'manifest_hash' => hash(
                'sha256',
                $manifestJson
            ),
            'created_at' =>
                '2026-08-02 13:00:00',
        ];
    }
}

final class ManifestCollectionDatabase implements
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
        if ($this->fetchException instanceof \RuntimeException) {
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
