<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\Manifest;

final class ManifestTest extends TestCase
{
    private const string HASH =
        '0123456789abcdef0123456789abcdef'
        . '0123456789abcdef0123456789abcdef';

    public function testExposesManifestData(): void
    {
        $createdAt = new DateTimeImmutable(
            '2026-08-01 12:00:00'
        );

        $manifest = new Manifest(
            42,
            15,
            '{"name":"example-plugin","version":"1.2.3"}',
            self::HASH,
            $createdAt
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
            $createdAt,
            $manifest->createdAt()
        );
    }

    public function testAcceptsUppercaseManifestHash(): void
    {
        $manifest = $this->manifest(
            manifestHash: strtoupper(self::HASH)
        );

        self::assertSame(
            strtoupper(self::HASH),
            $manifest->manifestHash()
        );
    }

    public function testRejectsInvalidIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest id must be a positive integer.'
        );

        $this->manifest(id: 0);
    }

    public function testRejectsInvalidReleaseIdentifier(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest releaseId must be a positive integer.'
        );

        $this->manifest(releaseId: 0);
    }

    public function testRejectsEmptyManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest manifestJson cannot be empty.'
        );

        $this->manifest(
            manifestJson: '   '
        );
    }

    public function testRejectsInvalidManifestJson(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest manifestJson must contain valid JSON.'
        );

        $this->manifest(
            manifestJson: '{"name":'
        );
    }

    public function testRejectsInvalidManifestHashLength(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Manifest manifestHash must contain 64 hexadecimal characters.'
        );

        $this->manifest(
            manifestHash: str_repeat('a', 63)
        );
    }

    public function testRejectsNonHexadecimalManifestHash(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->manifest(
            manifestHash: str_repeat('z', 64)
        );
    }

    private function manifest(
        int $id = 42,
        int $releaseId = 15,
        string $manifestJson = '{"name":"example-plugin"}',
        string $manifestHash = self::HASH
    ): Manifest {
        return new Manifest(
            $id,
            $releaseId,
            $manifestJson,
            $manifestHash,
            new DateTimeImmutable(
                '2026-08-01 12:00:00'
            )
        );
    }
}
