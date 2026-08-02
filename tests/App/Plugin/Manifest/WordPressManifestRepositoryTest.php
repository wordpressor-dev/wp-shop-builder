<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Manifest;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Manifest\ManifestRowMapper;
use WPShop\App\Plugin\Manifest\WordPressManifestRepository;
use WPShop\Manifest\Exception\ManifestPersistenceFailed;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestUpdateData;

final class WordPressManifestRepositoryTest extends TestCase
{
    private const string MANIFEST_JSON =
        '{"name":"example-plugin"}';

    public function testCreatesManifest(): void
    {
        $database = new RecordingManifestDatabase();
        $database->row = $this->row();

        $manifest = $this->repository($database)->create(
            new ManifestCreateData(
                7,
                self::MANIFEST_JSON
            )
        );

        self::assertSame(
            42,
            $manifest->id()
        );

        self::assertSame(
            'wp_wps_manifests',
            $database->insertTable
        );

        self::assertSame(
            [
                'release_id' => 7,
                'manifest_json' =>
                    self::MANIFEST_JSON,
                'manifest_hash' => hash(
                    'sha256',
                    self::MANIFEST_JSON
                ),
                'created_at' =>
                    '2026-08-02 13:00:00',
            ],
            $database->insertData
        );

        self::assertSame(
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ],
            $database->insertFormats
        );

        self::assertSame(
            [42],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE id = %d',
            $database->fetchSql
        );
    }

    public function testUpdatesManifest(): void
    {
        $manifestJson =
            '{"name":"example-plugin","version":"2.0.0"}';

        $database = new RecordingManifestDatabase();
        $database->updateResult = 1;
        $database->row = $this->row();
        $database->row['manifest_json'] = $manifestJson;
        $database->row['manifest_hash'] = hash(
            'sha256',
            $manifestJson
        );

        $manifest = $this->repository($database)->update(
            42,
            new ManifestUpdateData($manifestJson)
        );

        self::assertNotNull($manifest);

        self::assertSame(
            $manifestJson,
            $manifest->manifestJson()
        );

        self::assertSame(
            'wp_wps_manifests',
            $database->updateTable
        );

        self::assertSame(
            [
                'manifest_json' => $manifestJson,
                'manifest_hash' => hash(
                    'sha256',
                    $manifestJson
                ),
            ],
            $database->updateData
        );

        self::assertSame(
            [
                'id' => 42,
            ],
            $database->updateWhere
        );

        self::assertSame(
            [
                '%s',
                '%s',
            ],
            $database->updateFormats
        );

        self::assertSame(
            [
                '%d',
            ],
            $database->updateWhereFormats
        );

        self::assertSame(
            [42],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE id = %d',
            $database->fetchSql
        );
    }

    public function testReturnsNullForMissingUpdateTarget(): void
    {
        self::assertNull(
            $this->repository(
                new RecordingManifestDatabase()
            )->update(
                999,
                new ManifestUpdateData('{}')
            )
        );
    }

    public function testRejectsInvalidUpdateIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest identifier must be positive.'
        );

        $this->repository(
            new RecordingManifestDatabase()
        )->update(
            0,
            new ManifestUpdateData('{}')
        );
    }

    public function testWrapsDatabaseUpdateFailure(): void
    {
        $database = new RecordingManifestDatabase();
        $database->updateException =
            new RuntimeException(
                'Native update failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest 42 update failed.'
        );

        $this->repository($database)->update(
            42,
            new ManifestUpdateData('{}')
        );
    }

    public function testWrapsUpdatedManifestReloadFailure(): void
    {
        $database = new RecordingManifestDatabase();
        $database->updateResult = 1;
        $database->fetchException =
            new RuntimeException(
                'Native lookup failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest 42 update failed.'
        );

        $this->repository($database)->update(
            42,
            new ManifestUpdateData('{}')
        );
    }

    public function testWrapsMissingUpdatedManifest(): void
    {
        $database = new RecordingManifestDatabase();
        $database->updateResult = 1;

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest 42 update failed.'
        );

        $this->repository($database)->update(
            42,
            new ManifestUpdateData('{}')
        );
    }

    public function testFindsManifestByIdentifier(): void
    {
        $database = new RecordingManifestDatabase();
        $database->row = $this->row();

        $manifest = $this->repository($database)
            ->findById(42);

        self::assertNotNull($manifest);

        self::assertSame(
            7,
            $manifest->releaseId()
        );

        self::assertSame(
            [42],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE id = %d',
            $database->fetchSql
        );
    }

    public function testReturnsNullForMissingIdentifier(): void
    {
        $database = new RecordingManifestDatabase();

        self::assertNull(
            $this->repository($database)
                ->findById(999)
        );
    }

    public function testFindsManifestByReleaseIdentifier(): void
    {
        $database = new RecordingManifestDatabase();
        $database->row = $this->row();

        $manifest = $this->repository($database)
            ->findByReleaseId(7);

        self::assertNotNull($manifest);

        self::assertSame(
            42,
            $manifest->id()
        );

        self::assertSame(
            [7],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE release_id = %d',
            $database->fetchSql
        );
    }

    public function testReturnsNullForMissingReleaseIdentifier(): void
    {
        $database = new RecordingManifestDatabase();

        self::assertNull(
            $this->repository($database)
                ->findByReleaseId(999)
        );
    }

    public function testRejectsInvalidTableName(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest table name is invalid.'
        );

        new WordPressManifestRepository(
            new RecordingManifestDatabase(),
            new ManifestRowMapper(),
            'wp-wps-manifests',
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable()
        );
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest identifier must be positive.'
        );

        $this->repository(
            new RecordingManifestDatabase()
        )->findById(0);
    }

    public function testRejectsInvalidReleaseIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest release identifier must be positive.'
        );

        $this->repository(
            new RecordingManifestDatabase()
        )->findByReleaseId(0);
    }

    public function testWrapsDatabaseCreationFailure(): void
    {
        $database = new RecordingManifestDatabase();

        $database->insertException =
            new RuntimeException(
                'Native insert failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest creation failed.'
        );

        $this->repository($database)->create(
            new ManifestCreateData(
                7,
                self::MANIFEST_JSON
            )
        );
    }

    public function testWrapsMissingCreatedManifest(): void
    {
        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest creation failed.'
        );

        $this->repository(
            new RecordingManifestDatabase()
        )->create(
            new ManifestCreateData(
                7,
                self::MANIFEST_JSON
            )
        );
    }

    public function testWrapsIdentifierLookupFailure(): void
    {
        $database = new RecordingManifestDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native lookup failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest lookup by id "42" failed.'
        );

        $this->repository($database)
            ->findById(42);
    }

    public function testWrapsReleaseLookupFailure(): void
    {
        $database = new RecordingManifestDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native lookup failed.'
            );

        $this->expectException(
            ManifestPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Manifest lookup by release_id "7" failed.'
        );

        $this->repository($database)
            ->findByReleaseId(7);
    }

    private function repository(
        RecordingManifestDatabase $database
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
            'manifest_json' =>
                self::MANIFEST_JSON,
            'manifest_hash' => hash(
                'sha256',
                self::MANIFEST_JSON
            ),
            'created_at' =>
                '2026-08-02 13:00:00',
        ];
    }
}

final class RecordingManifestDatabase implements
    DatabaseConnectionInterface
{
    public string $insertTable = '';

    /**
     * @var array<string, int|float|string|null>
     */
    public array $insertData = [];

    /**
     * @var list<string>
     */
    public array $insertFormats = [];

    public int $insertId = 42;

    public string $fetchSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $fetchParameters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $row = null;

    public ?RuntimeException $insertException = null;

    public ?RuntimeException $fetchException = null;

    public string $updateTable = '';

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
    public array $updateFormats = [];

    /**
     * @var list<string>
     */
    public array $updateWhereFormats = [];

    public int $updateResult = 0;

    public ?RuntimeException $updateException = null;

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        if ($this->insertException instanceof \RuntimeException) {
            throw $this->insertException;
        }

        $this->insertTable = $table;
        $this->insertData = $data;
        $this->insertFormats = $formats;

        return $this->insertId;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        if ($this->updateException instanceof \RuntimeException) {
            throw $this->updateException;
        }

        $this->updateTable = $table;
        $this->updateData = $data;
        $this->updateWhere = $where;
        $this->updateFormats = $formats;
        $this->updateWhereFormats = $whereFormats;

        return $this->updateResult;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        if ($this->fetchException instanceof \RuntimeException) {
            throw $this->fetchException;
        }

        $this->fetchSql = $sql;
        $this->fetchParameters = $parameters;

        return $this->row;
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        return [];
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
