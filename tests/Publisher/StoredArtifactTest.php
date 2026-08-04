<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\StoredArtifact;

final class StoredArtifactTest extends TestCase
{
    private const string STORAGE_KEY =
        '123e4567-e89b-12d3-a456-426614174000/10/package.zip';

    private const string SHA256 =
        '0123456789abcdef0123456789abcdef'
        . '0123456789abcdef0123456789abcdef';

    public function testExposesStoredArtifact(): void
    {
        $artifact = $this->artifact();

        self::assertSame(
            self::STORAGE_KEY,
            $artifact->storageKey()
        );

        self::assertSame(
            'package.zip',
            $artifact->filename()
        );

        self::assertSame(
            'application/zip',
            $artifact->mediaType()
        );

        self::assertSame(
            123456,
            $artifact->size()
        );

        self::assertSame(
            self::SHA256,
            $artifact->sha256()
        );
    }

    #[DataProvider('unsafeStorageKeys')]
    public function testRejectsUnsafeStorageKey(
        string $storageKey
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Stored artifact storageKey must be a safe relative path.'
        );

        new StoredArtifact(
            $storageKey,
            'package.zip',
            'application/zip',
            1,
            self::SHA256
        );
    }

    public function testRejectsFilenameThatDoesNotMatchKey(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Stored artifact filename must match its storageKey.'
        );

        new StoredArtifact(
            self::STORAGE_KEY,
            'other.zip',
            'application/zip',
            1,
            self::SHA256
        );
    }

    public function testRejectsNegativeSize(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Stored artifact size cannot be negative.'
        );

        new StoredArtifact(
            self::STORAGE_KEY,
            'package.zip',
            'application/zip',
            -1,
            self::SHA256
        );
    }

    public function testRejectsInvalidSha256(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Stored artifact sha256 must be a lowercase SHA-256 checksum.'
        );

        new StoredArtifact(
            self::STORAGE_KEY,
            'package.zip',
            'application/zip',
            1,
            'invalid'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeStorageKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/artifact.zip'];
        yield 'trailing separator' => ['directory/'];
        yield 'empty segment' => ['directory//artifact.zip'];
        yield 'current segment' => ['directory/./artifact.zip'];
        yield 'parent segment' => ['directory/../artifact.zip'];
        yield 'backslash' => ['directory\\artifact.zip'];
    }

    private function artifact(): StoredArtifact
    {
        return new StoredArtifact(
            self::STORAGE_KEY,
            'package.zip',
            'application/zip',
            123456,
            self::SHA256
        );
    }
}
