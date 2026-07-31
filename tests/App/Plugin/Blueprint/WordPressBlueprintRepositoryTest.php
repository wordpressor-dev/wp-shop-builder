<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Blueprint;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Blueprint\BlueprintRowMapper;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final class WordPressBlueprintRepositoryTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testCreatesAndReloadsBlueprint(): void
    {
        $database = new RecordingBlueprintDatabase();
        $database->insertId = 42;
        $database->row = $this->validRow();

        $repository = $this->repository($database);

        $blueprint = $repository->create(
            new BlueprintCreateData(
                'example-plugin',
                'plugin',
                7,
                9
            )
        );

        self::assertSame(42, $blueprint->id());

        self::assertSame(
            'wp_wps_blueprints',
            $database->insertTable
        );

        self::assertSame(
            [
                'uuid' => self::UUID,
                'slug' => 'example-plugin',
                'type' => 'plugin',
                'state' => 'draft',
                'workflow' => 'default',
                'created_at' =>
                    '2026-07-31 10:00:00',
                'updated_at' =>
                    '2026-07-31 10:00:00',
                'provider_id' => 7,
                'developer_id' => 9,
            ],
            $database->insertData
        );

        self::assertSame(
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
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

        self::assertStringContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql
        );
    }

    public function testFindsBlueprintByIdentifier(): void
    {
        $database = new RecordingBlueprintDatabase();
        $database->row = $this->validRow();

        $blueprint = $this->repository($database)
            ->findById(42);

        self::assertNotNull($blueprint);
        self::assertSame(42, $blueprint->id());

        self::assertSame(
            [42],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE id = %d',
            $database->fetchSql
        );
    }

    public function testFindsBlueprintByUuid(): void
    {
        $database = new RecordingBlueprintDatabase();
        $database->row = $this->validRow();

        $blueprint = $this->repository($database)
            ->findByUuid(self::UUID);

        self::assertNotNull($blueprint);

        self::assertSame(
            self::UUID,
            $blueprint->uuid()
        );

        self::assertSame(
            [self::UUID],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE uuid = %s',
            $database->fetchSql
        );
    }

    public function testReturnsNullWhenBlueprintIsMissing(): void
    {
        $database = new RecordingBlueprintDatabase();
        $database->row = null;

        self::assertNull(
            $this->repository($database)
                ->findById(42)
        );
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $database = new RecordingBlueprintDatabase();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->repository($database)
            ->findById(0);
    }

    public function testWrapsCreationFailure(): void
    {
        $database = new RecordingBlueprintDatabase();

        $database->insertException =
            new RuntimeException(
                'Insert failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint creation failed.'
        );

        $this->repository($database)->create(
            new BlueprintCreateData(
                'example-plugin',
                'plugin'
            )
        );
    }

    private function repository(
        RecordingBlueprintDatabase $database
    ): WordPressBlueprintRepository {
        return new WordPressBlueprintRepository(
            $database,
            new BlueprintRowMapper(),
            'wp_wps_blueprints',
            static fn (): string => self::UUID,
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-07-31 10:00:00'
                )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validRow(): array
    {
        return [
            'id' => '42',
            'uuid' => self::UUID,
            'slug' => 'example-plugin',
            'type' => 'plugin',
            'provider_id' => '7',
            'developer_id' => '9',
            'current_release_id' => null,
            'state' => 'draft',
            'workflow' => 'default',
            'created_at' => '2026-07-31 10:00:00',
            'updated_at' => '2026-07-31 10:00:00',
            'deleted_at' => null,
        ];
    }
}

final class RecordingBlueprintDatabase implements
    DatabaseConnectionInterface
{
    public int $insertId = 1;

    public ?RuntimeException $insertException = null;

    public ?string $insertTable = null;

    /**
     * @var array<string, int|float|string|null>
     */
    public array $insertData = [];

    /**
     * @var list<string>
     */
    public array $insertFormats = [];

    public string $fetchSql = '';

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
        $this->fetchSql = $sql;
        $this->fetchParameters = $parameters;

        return $this->row;
    }
}
