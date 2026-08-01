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
use WPShop\Release\ReleaseUpdateData;

final class WordPressReleaseRepositoryUpdateTest extends TestCase
{
    public function testUpdatesRelease(): void
    {
        $database = new RecordingReleaseUpdateDatabase();
        $database->affectedRows = 1;
        $database->row = $this->row();

        $release = $this->repository($database)->update(
            42,
            new ReleaseUpdateData(
                '2.0.0',
                'published',
                15,
                true,
                99.5
            )
        );

        self::assertNotNull($release);

        self::assertSame(
            '2.0.0',
            $release->version()
        );

        self::assertSame(
            'published',
            $release->status()
        );

        self::assertSame(
            'wp_wps_releases',
            $database->updateTable
        );

        self::assertSame(
            [
                'version' => '2.0.0',
                'status' => 'published',
                'manifest_id' => 15,
                'published' => 1,
                'validation_score' => 99.5,
            ],
            $database->updateData
        );

        self::assertSame(
            ['id' => 42],
            $database->updateWhere
        );

        self::assertSame(
            [
                '%s',
                '%s',
                '%d',
                '%d',
                '%f',
            ],
            $database->updateFormats
        );

        self::assertSame(
            ['%d'],
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

    public function testUpdatesNullableFields(): void
    {
        $database = new RecordingReleaseUpdateDatabase();
        $database->affectedRows = 1;

        $database->row = $this->row(
            manifestId: null,
            published: '0',
            validationScore: null
        );

        $release = $this->repository($database)->update(
            42,
            new ReleaseUpdateData(
                '2.0.0',
                'draft',
                null,
                false,
                null
            )
        );

        self::assertNotNull($release);

        self::assertSame(
            [
                'version' => '2.0.0',
                'status' => 'draft',
                'manifest_id' => null,
                'published' => 0,
                'validation_score' => null,
            ],
            $database->updateData
        );

        self::assertNull(
            $release->manifestId()
        );

        self::assertFalse(
            $release->published()
        );

        self::assertNull(
            $release->validationScore()
        );
    }

    public function testReturnsExistingReleaseWhenDataIsUnchanged(): void
    {
        $database = new RecordingReleaseUpdateDatabase();
        $database->affectedRows = 0;
        $database->row = $this->row();

        $release = $this->repository($database)->update(
            42,
            $this->updateData()
        );

        self::assertNotNull($release);
        self::assertSame(42, $release->id());
    }

    public function testReturnsNullWhenReleaseDoesNotExist(): void
    {
        $database = new RecordingReleaseUpdateDatabase();
        $database->affectedRows = 0;

        $release = $this->repository($database)->update(
            999,
            $this->updateData()
        );

        self::assertNull($release);

        self::assertSame(
            [999],
            $database->fetchParameters
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
            new RecordingReleaseUpdateDatabase()
        )->update(
            0,
            $this->updateData()
        );
    }

    public function testWrapsDatabaseUpdateFailure(): void
    {
        $database = new RecordingReleaseUpdateDatabase();

        $database->updateException =
            new RuntimeException(
                'Native update failed.'
            );

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release 42 update failed.'
        );

        $this->repository($database)->update(
            42,
            $this->updateData()
        );
    }

    public function testWrapsMissingUpdatedRelease(): void
    {
        $database = new RecordingReleaseUpdateDatabase();
        $database->affectedRows = 1;

        $this->expectException(
            ReleasePersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Release 42 update failed.'
        );

        $this->repository($database)->update(
            42,
            $this->updateData()
        );
    }

    private function repository(
        RecordingReleaseUpdateDatabase $database
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

    private function updateData(): ReleaseUpdateData
    {
        return new ReleaseUpdateData(
            '2.0.0',
            'published',
            15,
            true,
            99.5
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        ?string $manifestId = '15',
        string $published = '1',
        ?string $validationScore = '99.50'
    ): array {
        return [
            'id' => '42',
            'blueprint_id' => '7',
            'version' => '2.0.0',
            'status' => $published === '1'
                ? 'published'
                : 'draft',
            'manifest_id' => $manifestId,
            'published' => $published,
            'validation_score' => $validationScore,
            'created_at' => '2026-08-01 12:00:00',
        ];
    }
}

final class RecordingReleaseUpdateDatabase implements
    DatabaseConnectionInterface
{
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

    public int $affectedRows = 0;

    public string $fetchSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $fetchParameters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $row = null;

    public ?RuntimeException $updateException = null;

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
        if ($this->updateException !== null) {
            throw $this->updateException;
        }

        $this->updateTable = $table;
        $this->updateData = $data;
        $this->updateWhere = $where;
        $this->updateFormats = $formats;
        $this->updateWhereFormats = $whereFormats;

        return $this->affectedRows;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
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
