<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\Contracts\ThemePackageValidatorInterface;
use WPShop\Publisher\Exception\ThemePackageValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\ThemeHeader;
use WPShop\Publisher\ThemePackageValidation;
use WPShop\Publisher\WordPressThemePublisher;
use WPShop\Release\Release;

final class WordPressThemePublisherTest extends TestCase
{
    public function testValidatesBeforePackageAssembly(): void
    {
        $blueprint = $this->blueprint();
        $release = $this->release();
        $source = $this->source();
        $artifact = $this->artifact();
        $validation = $this->validation();

        /** @var list<string> $operations */
        $operations = [];

        $resolver = $this->createMock(
            PackageSourceResolverInterface::class
        );

        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->with($blueprint, $release)
            ->willReturnCallback(
                static function () use (
                    &$operations,
                    $source
                ): PackageSource {
                    $operations[] = 'resolve';

                    return $source;
                }
            );

        $validator = $this->createMock(
            ThemePackageValidatorInterface::class
        );

        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($source, $release)
            ->willReturnCallback(
                static function () use (
                    &$operations,
                    $validation
                ): ThemePackageValidation {
                    $operations[] = 'validate';

                    return $validation;
                }
            );

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
            ->willReturnCallback(
                static function () use (
                    &$operations,
                    $artifact
                ): PublicationArtifact {
                    $operations[] = 'assemble';

                    return $artifact;
                }
            );

        $result = (
            new WordPressThemePublisher(
                $resolver,
                $validator,
                $assembler
            )
        )->publish(
            $blueprint,
            $release
        );

        self::assertSame(
            [
                'resolve',
                'validate',
                'assemble',
            ],
            $operations
        );

        self::assertSame(
            '{"type":"theme","slug":"example-theme",'
                . '"version":"1.0.0",'
                . '"entry":"example-theme/style.css"}',
            $result->manifestJson()
        );

        self::assertSame(
            87.5,
            $result->validationScore()
        );

        self::assertSame(
            $artifact,
            $result->artifact()
        );
    }

    public function testValidationFailurePreventsAssembly(): void
    {
        $source = $this->source();

        $resolver = $this->createMock(
            PackageSourceResolverInterface::class
        );

        $resolver
            ->method('resolve')
            ->willReturn($source);

        $failure =
            ThemePackageValidationFailed::versionMismatch(
                '1.0.0',
                '2.0.0'
            );

        $validator = $this->createMock(
            ThemePackageValidatorInterface::class
        );

        $validator
            ->expects(self::once())
            ->method('validate')
            ->willThrowException($failure);

        $assembler = $this->createMock(
            PackageAssemblerInterface::class
        );

        $assembler
            ->expects(self::never())
            ->method('assemble');

        try {
            (
                new WordPressThemePublisher(
                    $resolver,
                    $validator,
                    $assembler
                )
            )->publish(
                $this->blueprint(),
                $this->release()
            );

            self::fail(
                'Theme validation failure was not propagated.'
            );
        } catch (
            ThemePackageValidationFailed $exception
        ) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testRejectsNonThemeBlueprint(): void
    {
        $resolver = $this->createMock(
            PackageSourceResolverInterface::class
        );

        $resolver
            ->expects(self::never())
            ->method('resolve');

        $validator = $this->createMock(
            ThemePackageValidatorInterface::class
        );

        $validator
            ->expects(self::never())
            ->method('validate');

        $assembler = $this->createMock(
            PackageAssemblerInterface::class
        );

        $assembler
            ->expects(self::never())
            ->method('assemble');

        $publisher = new WordPressThemePublisher(
            $resolver,
            $validator,
            $assembler
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'WordPress theme publisher requires '
                . 'Blueprint type "theme".'
        );

        $publisher->publish(
            $this->blueprint('plugin'),
            $this->release()
        );
    }

    private function source(): PackageSource
    {
        return new PackageSource(
            '/tmp/example-source',
            'example-theme',
            'style.css'
        );
    }

    private function validation(): ThemePackageValidation
    {
        return new ThemePackageValidation(
            new ThemeHeader(
                'Example Theme',
                '1.0.0'
            ),
            87.5
        );
    }

    private function artifact(): PublicationArtifact
    {
        return new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            'application/zip'
        );
    }

    private function blueprint(
        string $type = 'theme'
    ): Blueprint {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-theme',
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
