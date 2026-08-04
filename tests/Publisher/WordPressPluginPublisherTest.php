<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\WordPressPluginPublisher;
use WPShop\Release\Release;

final class WordPressPluginPublisherTest extends TestCase
{
    public function testBuildsPluginManifestAndArtifact(): void
    {
        $blueprint = $this->blueprint();
        $release = $this->release();

        $source = new PackageSource(
            '/tmp/example-source',
            'example-plugin'
        );

        $artifact = new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            'application/zip'
        );

        $resolver = $this->createMock(
            PackageSourceResolverInterface::class
        );

        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->with($blueprint, $release)
            ->willReturn($source);

        $assembler = $this->createMock(
            PackageAssemblerInterface::class
        );

        $assembler
            ->expects(self::once())
            ->method('assemble')
            ->with(
                $blueprint,
                $release,
                $source
            )
            ->willReturn($artifact);

        $result = (new WordPressPluginPublisher(
            $resolver,
            $assembler
        ))->publish(
            $blueprint,
            $release
        );

        self::assertSame(
            '{"type":"plugin","slug":"example-plugin",'
                . '"version":"1.0.0",'
                . '"entry":"example-plugin/example-plugin.php"}',
            $result->manifestJson()
        );

        self::assertNull(
            $result->validationScore()
        );

        self::assertSame(
            $artifact,
            $result->artifact()
        );
    }

    public function testRejectsNonPluginBlueprint(): void
    {
        $resolver = $this->createMock(
            PackageSourceResolverInterface::class
        );

        $resolver
            ->expects(self::never())
            ->method('resolve');

        $assembler = $this->createMock(
            PackageAssemblerInterface::class
        );

        $assembler
            ->expects(self::never())
            ->method('assemble');

        $publisher = new WordPressPluginPublisher(
            $resolver,
            $assembler
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'WordPress plugin publisher requires '
                . 'Blueprint type "plugin".'
        );

        $publisher->publish(
            $this->blueprint('theme'),
            $this->release()
        );
    }

    private function blueprint(
        string $type = 'plugin'
    ): Blueprint {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-plugin',
            $type,
            null,
            null,
            null,
            'active',
            'draft',
            new DateTimeImmutable(
                '2026-08-01 10:00:00'
            ),
            new DateTimeImmutable(
                '2026-08-02 10:00:00'
            ),
            null
        );
    }

    private function release(): Release
    {
        return new Release(
            10,
            5,
            '1.0.0',
            'draft',
            null,
            false,
            null,
            new DateTimeImmutable(
                '2026-08-03 10:00:00'
            )
        );
    }
}
