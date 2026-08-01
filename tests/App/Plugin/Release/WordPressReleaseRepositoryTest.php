<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Release\ReleaseRowMapper;
use WPShop\App\Plugin\Release\WordPressReleaseRepository;
use WPShop\Release\Exception\ReleasePersistenceFailed;
use WPShop\Release\ReleaseCreateData;

final class WordPressReleaseRepositoryTest extends TestCase
{
    public function testCreatesRelease(): void
    {
        $database = new RecordingReleaseDatabase();
        $database->row = $this->row();

        $release = $this->repository($database)->create(
            new ReleaseCreateData(
                7,
                '1.2.3',
                'published',
                15,
                true,
                98.75
            )
        );

        self::assertSame(42, $release->id());

        self::assertSame(
            'wp_wps_releases',
            $database->insertTable
        );

        self::assertSame(
            [
                'blueprint_id' => 7,
                'version' => '1.2.3',
                'status' => 'published',
                'published' => 1,
                'created_at' =>
                    '2026-08-01 12:00:00',
                'manifest_id' => 15,
                'validation_score' => 98.75,
            ],
            $database->insertData
        );

        self::assertSame(
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%f',
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

    public function testCreatesReleaseWithDefaults(): void
    {
        $database = new RecordingReleaseDatabase();

        $database->row = $this->row(
            status: 'draft',
            manifestId: null,
            published: '0',
            validationScore: null
        );

        $release = $this->repository($database)->create(
            new ReleaseCreateData(
                7,
                '1.0.0'
            )
        );

        self::assertSame(
            'draft',
            $release->status()
        );

        self::assertArrayNotHasKey(
            'manifest_id',
            $database->insertData
        );

        self::assertArrayNotHasKey(
            'validation_score',
            $database->insertData
        );

        self::assertSame(
            0,
            $database->insertData['published']
        );
    }

    public function testWrapsCreationFailure(): void
    {
        $database = new RecordingReleaseDatabase();

        $database->insertException =
            new RuntimeException(
                'Native insert failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release creation failed.'
        );

        $this->repository($database)->create(
            new ReleaseCreateData(
                7,
                '1.2.3'
            )
        );
    }

    public function testWrapsMissingCreatedRelease(): void
    {
        $database = new RecordingReleaseDatabase();

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release creation failed.'
        );

        $this->repository($database)->create(
            new ReleaseCreateData(
                7,
                '1.2.3'
            )
        );
    }

    public function testFindsReleaseByIdentifier(): void
    {
        $database = new RecordingReleaseDatabase();
        $database->row = $this->row();

        $release = $this->repository($database)
            ->findById(42);

        self::assertNotNull($release);
        self::assertSame(42, $release->id());

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
        $database = new RecordingReleaseDatabase();

        self::assertNull(
            $this->repository($database)
                ->findById(999)
        );
    }

    public function testFindsReleaseByBlueprintAndVersion(): void
    {
        $database = new RecordingReleaseDatabase();
        $database->row = $this->row();

        $release = $this->repository($database)
            ->findByBlueprintAndVersion(
                7,
                '1.2.3'
            );

        self::assertNotNull($release);

        self::assertSame(
            [
                7,
                '1.2.3',
            ],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE blueprint_id = %d',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'AND version = %s',
            $database->fetchSql
        );
    }

    public function testReturnsNullForMissingBlueprintVersion(): void
    {
        $database = new RecordingReleaseDatabase();

        self::assertNull(
            $this->repository($database)
                ->findByBlueprintAndVersion(
                    7,
                    '9.9.9'
                )
        );
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release identifier must be positive.'
        );

        $this->repository(
            new RecordingReleaseDatabase()
        )->findById(0);
    }

    public function testRejectsInvalidBlueprintIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release blueprint identifier must be positive.'
        );

        $this->repository(
            new RecordingReleaseDatabase()
        )->findByBlueprintAndVersion(
            0,
            '1.2.3'
        );
    }

    public function testRejectsInvalidVersion(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Release version must contain between 1 and 64 characters.'
        );

        $this->repository(
            new RecordingReleaseDatabase()
        )->findByBlueprintAndVersion(
            7,
            '   '
        );
    }

    public function testWrapsIdentifierLookupFailure(): void
    {
        $database = new RecordingReleaseDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native lookup failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release lookup by id "42" failed.'
        );

        $this->repository($database)
            ->findById(42);
    }

    public function testWrapsBlueprintVersionLookupFailure(): void
    {
        $database = new RecordingReleaseDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native lookup failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release lookup by blueprint 7 and version "1.2.3" failed.'
        );

        $this->repository($database)
            ->findByBlueprintAndVersion(
                7,
                '1.2.3'
            );
    }

    private function repository(
        RecordingReleaseDatabase $database
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
        string $status = 'published',
        ?string $manifestId = '15',
        string $published = '1',
        ?string $validationScore = '98.75'
    ): array {
        return [
            'id' => '42',
            'blueprint_id' => '7',
            'version' => '1.2.3',
            'status' => $status,
            'manifest_id' => $manifestId,
            'published' => $published,
            'validation_score' => $validationScore,
            'created_at' => '2026-08-01 12:00:00',
        ];
    }
}

final class RecordingReleaseDatabase implements
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

    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        if ($this->insertException !== null) {
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
        return 0;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        if ($this->fetchException !== null) {
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
