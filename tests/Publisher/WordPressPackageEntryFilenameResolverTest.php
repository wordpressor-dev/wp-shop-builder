<?php

declare(strict_types=1);

namespace WPShop\Tests\Publisher;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Exception\PackageEntryResolutionFailed;
use WPShop\Publisher\Resolution\WordPressPackageEntryFilenameResolver;

final class WordPressPackageEntryFilenameResolverTest extends TestCase
{
    #[DataProvider('supportedBlueprints')]
    public function testResolvesWordPressEntryFilename(
        string $type,
        string $slug,
        string $entryFilename
    ): void {
        self::assertSame(
            $entryFilename,
            (new WordPressPackageEntryFilenameResolver())
                ->resolve(
                    $this->blueprint($type, $slug)
                )
        );
    }

    public function testRejectsUnsupportedBlueprintType(): void
    {
        $this->expectException(
            PackageEntryResolutionFailed::class
        );

        $this->expectExceptionMessage(
            'Package entry filename cannot be resolved '
                . 'for Blueprint type "block".'
        );

        (new WordPressPackageEntryFilenameResolver())
            ->resolve(
                $this->blueprint('block', 'example-block')
            );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function supportedBlueprints(): iterable
    {
        yield 'plugin' => [
            'plugin',
            'example-plugin',
            'example-plugin.php',
        ];

        yield 'theme' => [
            'theme',
            'example-theme',
            'style.css',
        ];
    }

    private function blueprint(
        string $type,
        string $slug
    ): Blueprint {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            $slug,
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
}
