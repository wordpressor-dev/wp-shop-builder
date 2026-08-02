<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Manifest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use WPShop\App\Plugin\Manifest\ManifestRowMapper;

final class ManifestRowMapperTest extends TestCase
{
    private const string HASH =
        '0123456789abcdef0123456789abcdef'
        . '0123456789abcdef0123456789abcdef';

    public function testMapsManifestRow(): void
    {
        $manifest = (new ManifestRowMapper())->map(
            $this->row()
        );

        self::assertSame(42, $manifest->id());
        self::assertSame(15, $manifest->releaseId());

        self::assertSame(
            '{"name":"example-plugin","version":"1.2.3"}',
            $manifest->manifestJson()
        );

        self::assertSame(
            self::HASH,
            $manifest->manifestHash()
        );

        self::assertSame(
            '2026-08-01 12:00:00',
            $manifest->createdAt()->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function testMapsNativeIdentifierValues(): void
    {
        $row = $this->row();

        $row['id'] = 42;
        $row['release_id'] = 15;

        $manifest = (new ManifestRowMapper())->map(
            $row
        );

        self::assertSame(42, $manifest->id());
        self::assertSame(15, $manifest->releaseId());
    }

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
                'Manifest database field "%s" is invalid.',
                $field
            )
        );

        (new ManifestRowMapper())->map($row);
    }

    public function testRejectsMissingDatabaseField(): void
    {
        $row = $this->row();

        unset($row['manifest_json']);

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Manifest database field "manifest_json" is invalid.'
        );

        (new ManifestRowMapper())->map($row);
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

        yield 'invalid release identifier' => [
            'release_id',
            0,
        ];

        yield 'invalid manifest json type' => [
            'manifest_json',
            [],
        ];

        yield 'malformed manifest json' => [
            'manifest_json',
            '{"name":',
        ];

        yield 'invalid manifest hash type' => [
            'manifest_hash',
            null,
        ];

        yield 'invalid manifest hash length' => [
            'manifest_hash',
            str_repeat('a', 63),
        ];

        yield 'non-hexadecimal manifest hash' => [
            'manifest_hash',
            str_repeat('z', 64),
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
            'release_id' => '15',
            'manifest_json' =>
                '{"name":"example-plugin","version":"1.2.3"}',
            'manifest_hash' => self::HASH,
            'created_at' => '2026-08-01 12:00:00',
        ];
    }
}
