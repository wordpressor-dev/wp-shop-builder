<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use WPShop\App\Plugin\Release\ReleaseRowMapper;

final class ReleaseRowMapperTest extends TestCase
{
    public function testMapsReleaseRow(): void
    {
        $release = (new ReleaseRowMapper())->map(
            $this->row()
        );

        self::assertSame(42, $release->id());

        self::assertSame(
            7,
            $release->blueprintId()
        );

        self::assertSame(
            '1.2.3',
            $release->version()
        );

        self::assertSame(
            'published',
            $release->status()
        );

        self::assertSame(
            15,
            $release->manifestId()
        );

        self::assertTrue(
            $release->published()
        );

        self::assertSame(
            98.75,
            $release->validationScore()
        );

        self::assertSame(
            '2026-08-01 12:00:00',
            $release->createdAt()->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function testMapsNullableReleaseFields(): void
    {
        $row = $this->row();

        $row['manifest_id'] = null;
        $row['published'] = '0';
        $row['validation_score'] = null;

        $release = (new ReleaseRowMapper())->map(
            $row
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

    public function testMapsNativeNumericValues(): void
    {
        $row = $this->row();

        $row['id'] = 42;
        $row['blueprint_id'] = 7;
        $row['manifest_id'] = 15;
        $row['published'] = 1;
        $row['validation_score'] = 100;

        $release = (new ReleaseRowMapper())->map(
            $row
        );

        self::assertSame(42, $release->id());

        self::assertSame(
            7,
            $release->blueprintId()
        );

        self::assertSame(
            15,
            $release->manifestId()
        );

        self::assertTrue(
            $release->published()
        );

        self::assertSame(
            100.0,
            $release->validationScore()
        );
    }

    /**
     * @param mixed $value
     */
    #[DataProvider('invalidFieldProvider')]
    public function testRejectsInvalidDatabaseField(
        string $field,
        mixed $value
    ): void {
        $row = $this->row();
        $row[$field] = $value;

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Release database field "%s" is invalid.',
                $field
            )
        );

        (new ReleaseRowMapper())->map($row);
    }

    public function testRejectsMissingDatabaseField(): void
    {
        $row = $this->row();

        unset($row['version']);

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Release database field "version" is invalid.'
        );

        (new ReleaseRowMapper())->map($row);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidFieldProvider(): iterable
    {
        yield 'invalid identifier' => [
            'id',
            '0',
        ];

        yield 'invalid blueprint identifier' => [
            'blueprint_id',
            0,
        ];

        yield 'invalid version' => [
            'version',
            123,
        ];

        yield 'invalid status' => [
            'status',
            null,
        ];

        yield 'invalid manifest identifier' => [
            'manifest_id',
            '0',
        ];

        yield 'invalid published flag' => [
            'published',
            '2',
        ];

        yield 'invalid validation score text' => [
            'validation_score',
            'invalid',
        ];

        yield 'validation score above maximum' => [
            'validation_score',
            '100.01',
        ];

        yield 'invalid creation date' => [
            'created_at',
            '2026-02-30 12:00:00',
        ];
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
