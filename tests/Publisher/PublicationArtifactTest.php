<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Publisher\PublicationArtifact;

final class PublicationArtifactTest extends TestCase
{
    public function testExposesPublicationArtifact(): void
    {
        $artifact = new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            'application/zip'
        );

        self::assertSame(
            '/tmp/package.zip',
            $artifact->sourcePath()
        );

        self::assertSame(
            'package.zip',
            $artifact->filename()
        );

        self::assertSame(
            'application/zip',
            $artifact->mediaType()
        );
    }

    public function testRejectsEmptySourcePath(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication artifact sourcePath cannot be empty.'
        );

        new PublicationArtifact(
            '   ',
            'package.zip',
            'application/zip'
        );
    }

    #[DataProvider('unsafeFilenames')]
    public function testRejectsUnsafeFilename(
        string $filename
    ): void {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication artifact filename must be a safe filename.'
        );

        new PublicationArtifact(
            '/tmp/package.zip',
            $filename,
            'application/zip'
        );
    }

    public function testRejectsEmptyMediaType(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Publication artifact mediaType cannot be empty.'
        );

        new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            '   '
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeFilenames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'current directory' => ['.'];
        yield 'parent directory' => ['..'];
        yield 'forward traversal' => ['../package.zip'];
        yield 'backward traversal' => ['..\\package.zip'];
        yield 'forward directory' => ['nested/package.zip'];
        yield 'backward directory' => ['nested\\package.zip'];
        yield 'embedded traversal' => ['package..zip'];
    }
}
