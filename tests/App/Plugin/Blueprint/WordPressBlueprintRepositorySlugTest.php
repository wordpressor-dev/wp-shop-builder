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
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final class WordPressBlueprintRepositorySlugTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testFindsActiveBlueprintBySlug(): void
    {
        $database = new SlugLookupDatabase();
        $database->row = $this->row();

        $blueprint = $this->repository($database)
            ->findBySlug('example-plugin');

        self::assertNotNull($blueprint);

        self::assertSame(
            'example-plugin',
            $blueprint->slug()
        );

        self::assertSame(
            ['example-plugin'],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE slug = %s',
            $database->fetchSql
        );

        self::assertStringContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql
        );
    }

    public function testFindsDeletedBlueprintBySlug(): void
    {
        $database = new SlugLookupDatabase();

        $database->row = $this->row(
            '2026-08-01 10:00:00'
        );

        $blueprint = $this->repository($database)
            ->findBySlugIncludingDeleted(
                'example-plugin'
            );

        self::assertNotNull($blueprint);
        self::assertNotNull($blueprint->deletedAt());

        self::assertSame(
            ['example-plugin'],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'WHERE slug = %s',
            $database->fetchSql
        );

        self::assertStringNotContainsString(
            'deleted_at IS NULL',
            $database->fetchSql
        );
    }

    public function testReturnsNullWhenSlugDoesNotExist(): void
    {
        $database = new SlugLookupDatabase();

        $blueprint = $this->repository($database)
            ->findBySlug('missing-plugin');

        self::assertNull($blueprint);

        self::assertSame(
            ['missing-plugin'],
            $database->fetchParameters
        );

        self::assertStringContainsString(
            'AND deleted_at IS NULL',
            $database->fetchSql
        );
    }

    public function testRejectsEmptySlug(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint slug must contain between 1 and 191 characters.'
        );

        $this->repository(
            new SlugLookupDatabase()
        )->findBySlug('   ');
    }

    public function testRejectsSlugAboveMaximumLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Blueprint slug must contain between 1 and 191 characters.'
        );

        $this->repository(
            new SlugLookupDatabase()
        )->findBySlug(
            str_repeat('a', 192)
        );
    }

    public function testWrapsSlugLookupFailure(): void
    {
        $database = new SlugLookupDatabase();

        $database->fetchException =
            new RuntimeException(
                'Native slug lookup failed.'
            );

        $this->expectException(
            BlueprintPersistenceFailed::class
        );

        $this->expectExceptionMessage(
            'Blueprint lookup by slug "example-plugin" failed.'
        );

        $this->repository($database)
            ->findBySlug('example-plugin');
    }

    private function repository(
        SlugLookupDatabase $database
    ): WordPressBlueprintRepository {
        return new WordPressBlueprintRepository(
            $database,
            new BlueprintRowMapper(),
            'wp_wps_blueprints',
            static fn (): string => self::UUID,
            static fn (): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 10:00:00'
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
            'state' => 'draft',
            'workflow' => 'default',
            'created_at' => '2026-08-01 09:00:00',
            'updated_at' => '2026-08-01 10:00:00',
            'deleted_at' => $deletedAt,
        ];
    }
}

final class SlugLookupDatabase implements
    DatabaseConnectionInterface
{
    public string $fetchSql = '';

    /**
     * @var list<int|float|string>
     */
    public array $fetchParameters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $row = null;

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
        $this->fetchSql = $sql;
        $this->fetchParameters = $parameters;

        if ($this->fetchException !== null) {
            throw $this->fetchException;
        }

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
