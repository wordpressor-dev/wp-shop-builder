<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use FilesystemIterator;
use PharData;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WPShop\App\Plugin\Release\ReleasePublisherService;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Publisher\Assembly\PharZipPackageAssembler;
use WPShop\Publisher\Manifest\JsonArtifactManifestDecorator;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;
use WPShop\Publisher\PublisherRegistry;
use WPShop\Publisher\Resolution\WordPressPackageEntryFilenameResolver;
use WPShop\Publisher\Source\LocalPackageSourceResolver;
use WPShop\Publisher\Storage\LocalArtifactStorage;
use WPShop\Publisher\Validation\WordPressThemePackageValidator;
use WPShop\Publisher\Validation\WordPressThemeStructureValidator;
use WPShop\Publisher\WordPressThemePublisher;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;

final class ConcreteThemePublicationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'wp-shop-builder-concrete-theme-publication-'
            . bin2hex(random_bytes(8));

        self::assertTrue(
            mkdir($this->directory, 0775, true)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testPublishesConcreteThemePackage(): void
    {
        $blueprint = $this->blueprint();
        $release = $this->release();
        $publishedRelease = $this->publishedRelease();

        $sourceDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . 'sources'
            . DIRECTORY_SEPARATOR
            . $blueprint->uuid()
            . DIRECTORY_SEPARATOR
            . $release->id();

        self::assertTrue(
            mkdir($sourceDirectory, 0775, true)
        );

        $styleContents = "/*\n"
            . "Theme Name: Example Theme\n"
            . "Version: 1.0.0\n"
            . "*/\n";

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . 'style.css',
                $styleContents
            )
        );

        $indexContents = "<?php\n";

        self::assertIsInt(
            file_put_contents(
                $sourceDirectory
                    . DIRECTORY_SEPARATOR
                    . 'index.php',
                $indexContents
            )
        );

        $publisher = new WordPressThemePublisher(
            new LocalPackageSourceResolver(
                $this->directory
                    . DIRECTORY_SEPARATOR
                    . 'sources',
                new WordPressPackageEntryFilenameResolver()
            ),
            new WordPressThemePackageValidator(
                new WordPressThemeHeaderParser(),
                new WordPressThemeStructureValidator()
            ),
            new PharZipPackageAssembler(
                $this->directory
                    . DIRECTORY_SEPARATOR
                    . 'work'
            )
        );

        $registry = new PublisherRegistry();
        $registry->register(
            WordPressThemePublisher::BLUEPRINT_TYPE,
            $publisher
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->method('findById')
            ->willReturn($release);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->method('findById')
            ->willReturn($blueprint);

        $policy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $artifactPath = $this->directory
            . DIRECTORY_SEPARATOR
            . 'artifacts'
            . DIRECTORY_SEPARATOR
            . $blueprint->uuid()
            . DIRECTORY_SEPARATOR
            . $release->id()
            . DIRECTORY_SEPARATOR
            . 'package.zip';

        $publicationService
            ->expects(self::once())
            ->method('publish')
            ->with(
                self::callback(
                    static function (
                        ReleasePublicationData $data
                    ) use ($artifactPath): bool {
                        $decoded = json_decode(
                            $data->manifestJson(),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );

                        if (! is_array($decoded)) {
                            self::fail(
                                'Published manifest must decode to an array.'
                            );
                        }

                        /** @var array<string, mixed> $manifest */
                        $manifest = $decoded;

                        self::assertSame(
                            'theme',
                            $manifest['type'] ?? null
                        );

                        self::assertSame(
                            'example-theme',
                            $manifest['slug'] ?? null
                        );

                        self::assertSame(
                            '1.0.0',
                            $manifest['version'] ?? null
                        );

                        self::assertSame(
                            'example-theme/style.css',
                            $manifest['entry'] ?? null
                        );

                        $artifact = $manifest['_artifact']
                            ?? null;

                        if (! is_array($artifact)) {
                            self::fail(
                                'Published manifest must contain artifact metadata.'
                            );
                        }

                        self::assertSame(
                            'package.zip',
                            $artifact['filename'] ?? null
                        );

                        self::assertSame(
                            'application/zip',
                            $artifact['mediaType'] ?? null
                        );

                        self::assertSame(
                            hash_file('sha256', $artifactPath),
                            $artifact['sha256'] ?? null
                        );

                        self::assertSame(
                            filesize($artifactPath),
                            $artifact['size'] ?? null
                        );

                        self::assertSame(
                            100.0,
                            $data->validationScore()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($publishedRelease);

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $policy,
            $registry,
            new LocalArtifactStorage(
                $this->directory
                    . DIRECTORY_SEPARATOR
                    . 'artifacts'
            ),
            new JsonArtifactManifestDecorator(),
            $publicationService
        );

        self::assertSame(
            $publishedRelease,
            $service->publish($release->id())
        );

        self::assertFileExists($artifactPath);

        $archive = new PharData($artifactPath);

        self::assertTrue(
            isset(
                $archive[
                    'example-theme/style.css'
                ]
            )
        );

        self::assertSame(
            $styleContents,
            $archive[
                'example-theme/style.css'
            ]->getContent()
        );

        self::assertTrue(
            isset(
                $archive[
                    'example-theme/index.php'
                ]
            )
        );

        self::assertSame(
            $indexContents,
            $archive[
                'example-theme/index.php'
            ]->getContent()
        );

        unset($archive);

        self::assertFileExists(
            $sourceDirectory
                . DIRECTORY_SEPARATOR
                . 'style.css'
        );

        self::assertFileExists(
            $sourceDirectory
                . DIRECTORY_SEPARATOR
                . 'index.php'
        );

        self::assertFileDoesNotExist(
            $this->directory
                . DIRECTORY_SEPARATOR
                . 'work'
                . DIRECTORY_SEPARATOR
                . $blueprint->uuid()
                . DIRECTORY_SEPARATOR
                . $release->id()
                . DIRECTORY_SEPARATOR
                . 'package.zip'
        );
    }

    private function blueprint(): Blueprint
    {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-theme',
            'theme',
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

    private function publishedRelease(): Release
    {
        return new Release(
            10,
            5,
            '1.0.0',
            'published',
            20,
            true,
            100.0,
            new DateTimeImmutable(
                '2026-08-03 10:00:00'
            )
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (! file_exists($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();

            if ($entry->isLink() || $entry->isFile()) {
                @unlink($path);

                continue;
            }

            @rmdir($path);
        }

        @rmdir($directory);
    }
}
