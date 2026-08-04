<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Release\ReleasePublisherService;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Publisher\Contracts\ArtifactManifestDecoratorInterface;
use WPShop\Publisher\Contracts\ArtifactStorageInterface;
use WPShop\Publisher\Contracts\PublisherInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Exception\ArtifactCleanupFailed;
use WPShop\Publisher\Exception\ArtifactStorageFailed;
use WPShop\Publisher\Exception\PublisherNotFound;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Publisher\PublicationResult;
use WPShop\Publisher\StoredArtifact;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Exception\ReleasePublicationNotAllowed;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;

final class ReleasePublisherServiceTest extends TestCase
{
    public function testPublishesStoredArtifactManifest(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $artifact = $this->artifact();
        $storedArtifact = $this->storedArtifact();

        $publishedRelease = $this->release(
            published: true
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(10)
            ->willReturn($release);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publicationPolicy
            ->expects(self::once())
            ->method('assertCanPublish')
            ->with(
                $blueprint,
                $release
            );

        $publisher = $this->createMock(
            PublisherInterface::class
        );

        $publisher
            ->expects(self::once())
            ->method('publish')
            ->with(
                $blueprint,
                $release
            )
            ->willReturn(
                new PublicationResult(
                    '{"package":"example"}',
                    98.75,
                    $artifact
                )
            );

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->expects(self::once())
            ->method('publisherFor')
            ->with('plugin')
            ->willReturn($publisher);

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::once())
            ->method('store')
            ->with(
                $blueprint,
                $release,
                $artifact
            )
            ->willReturn($storedArtifact);

        $artifactStorage
            ->expects(self::never())
            ->method('delete');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->expects(self::once())
            ->method('decorate')
            ->with(
                '{"package":"example"}',
                $storedArtifact
            )
            ->willReturn(
                '{"package":"example","_artifact":{"key":"stored"}}'
            );

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::once())
            ->method('publish')
            ->with(
                self::callback(
                    static function (
                        ReleasePublicationData $data
                    ): bool {
                        self::assertSame(
                            10,
                            $data->releaseId()
                        );

                        self::assertSame(
                            '{"package":"example",'
                                . '"_artifact":{"key":"stored"}}',
                            $data->manifestJson()
                        );

                        self::assertSame(
                            98.75,
                            $data->validationScore()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($publishedRelease);

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        self::assertSame(
            $publishedRelease,
            $service->publish(10)
        );
    }

    public function testMissingReleasePreventsPublication(): void
    {
        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(10)
            ->willReturn(null);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::never())
            ->method('findById');

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publicationPolicy
            ->expects(self::never())
            ->method('assertCanPublish');

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->expects(self::never())
            ->method('publisherFor');

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::never())
            ->method('store');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->expects(self::never())
            ->method('decorate');

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        $this->expectException(
            ReleaseNotFound::class
        );

        $this->expectExceptionMessage(
            'Release with identifier 10 was not found.'
        );

        $service->publish(10);
    }

    public function testMissingBlueprintPreventsPublication(): void
    {
        $release = $this->release();

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
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn(null);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publicationPolicy
            ->expects(self::never())
            ->method('assertCanPublish');

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->expects(self::never())
            ->method('publisherFor');

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::never())
            ->method('store');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->expects(self::never())
            ->method('decorate');

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 5 was not found.'
        );

        $service->publish(10);
    }

    public function testPolicyFailurePreventsPublisherExecution(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();

        $failure = ReleasePublicationNotAllowed::alreadyPublished(
            $release->id()
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publicationPolicy
            ->expects(self::once())
            ->method('assertCanPublish')
            ->willThrowException($failure);

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->expects(self::never())
            ->method('publisherFor');

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::never())
            ->method('store');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Publication policy failure was not propagated.'
            );
        } catch (ReleasePublicationNotAllowed $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testUnknownPublisherPreventsArtifactStorage(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();

        $failure = PublisherNotFound::forBlueprintType(
            'plugin'
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->expects(self::once())
            ->method('publisherFor')
            ->with('plugin')
            ->willThrowException($failure);

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::never())
            ->method('store');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Unknown publisher failure was not propagated.'
            );
        } catch (PublisherNotFound $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testPublisherFailurePreventsArtifactStorage(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();

        $failure = new RuntimeException(
            'Publisher failed.'
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisher = $this->createMock(
            PublisherInterface::class
        );

        $publisher
            ->expects(self::once())
            ->method('publish')
            ->willThrowException($failure);

        $publisherRegistry = $this->publisherRegistry($publisher);

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::never())
            ->method('store');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Publisher failure was not propagated.'
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testStorageFailurePreventsPersistence(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();

        $failure = ArtifactStorageFailed::targetCreationFailed(
            'artifact-key'
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisher = $this->publisher();
        $publisherRegistry = $this->publisherRegistry($publisher);

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->expects(self::once())
            ->method('store')
            ->willThrowException($failure);

        $artifactStorage
            ->expects(self::never())
            ->method('delete');

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->expects(self::never())
            ->method('decorate');

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Artifact storage failure was not propagated.'
            );
        } catch (ArtifactStorageFailed $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testDecorationFailureDeletesStoredArtifact(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $storedArtifact = $this->storedArtifact();

        $failure = new RuntimeException(
            'Manifest decoration failed.'
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisherRegistry = $this->publisherRegistry(
            $this->publisher()
        );

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->method('store')
            ->willReturn($storedArtifact);

        $artifactStorage
            ->expects(self::once())
            ->method('delete')
            ->with($storedArtifact);

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->expects(self::once())
            ->method('decorate')
            ->willThrowException($failure);

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Manifest decoration failure was not propagated.'
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testPersistenceFailureDeletesStoredArtifact(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $storedArtifact = $this->storedArtifact();

        $failure = new RuntimeException(
            'Publication persistence failed.'
        );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisherRegistry = $this->publisherRegistry(
            $this->publisher()
        );

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->method('store')
            ->willReturn($storedArtifact);

        $artifactStorage
            ->expects(self::once())
            ->method('delete')
            ->with($storedArtifact);

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->method('decorate')
            ->willReturn('{"_artifact":{"key":"stored"}}');

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::once())
            ->method('publish')
            ->willThrowException($failure);

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Publication persistence failure was not propagated.'
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                $failure,
                $exception
            );
        }
    }

    public function testCleanupFailurePreservesBothFailures(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $storedArtifact = $this->storedArtifact();

        $initialFailure = new RuntimeException(
            'Publication persistence failed.'
        );

        $cleanupFailure =
            ArtifactStorageFailed::deletionFailed(
                $storedArtifact->storageKey()
            );

        $releaseRepository = $this->releaseRepository($release);
        $blueprintRepository =
            $this->blueprintRepository($blueprint);

        $publicationPolicy = $this->createMock(
            ReleasePublicationPolicyInterface::class
        );

        $publisherRegistry = $this->publisherRegistry(
            $this->publisher()
        );

        $artifactStorage = $this->createMock(
            ArtifactStorageInterface::class
        );

        $artifactStorage
            ->method('store')
            ->willReturn($storedArtifact);

        $artifactStorage
            ->expects(self::once())
            ->method('delete')
            ->willThrowException($cleanupFailure);

        $manifestDecorator = $this->createMock(
            ArtifactManifestDecoratorInterface::class
        );

        $manifestDecorator
            ->method('decorate')
            ->willReturn('{"_artifact":{"key":"stored"}}');

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->method('publish')
            ->willThrowException($initialFailure);

        $service = $this->service(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );

        try {
            $service->publish(10);
            self::fail(
                'Artifact cleanup failure was not raised.'
            );
        } catch (ArtifactCleanupFailed $exception) {
            self::assertSame(
                $initialFailure,
                $exception->initialFailure()
            );

            self::assertSame(
                $cleanupFailure,
                $exception->cleanupFailure()
            );

            self::assertSame(
                $initialFailure,
                $exception->getPrevious()
            );
        }
    }

    private function service(
        ReleaseRepositoryInterface $releaseRepository,
        BlueprintRepositoryInterface $blueprintRepository,
        ReleasePublicationPolicyInterface $publicationPolicy,
        PublisherRegistryInterface $publisherRegistry,
        ArtifactStorageInterface $artifactStorage,
        ArtifactManifestDecoratorInterface $manifestDecorator,
        ReleasePublicationServiceInterface $publicationService
    ): ReleasePublisherService {
        return new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
            $artifactStorage,
            $manifestDecorator,
            $publicationService
        );
    }

    private function releaseRepository(
        Release $release
    ): ReleaseRepositoryInterface {
        $repository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $repository
            ->method('findById')
            ->willReturn($release);

        return $repository;
    }

    private function blueprintRepository(
        Blueprint $blueprint
    ): BlueprintRepositoryInterface {
        $repository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $repository
            ->method('findById')
            ->willReturn($blueprint);

        return $repository;
    }

    private function publisherRegistry(
        PublisherInterface $publisher
    ): PublisherRegistryInterface {
        $registry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $registry
            ->method('publisherFor')
            ->willReturn($publisher);

        return $registry;
    }

    private function publisher(): PublisherInterface
    {
        $publisher = $this->createMock(
            PublisherInterface::class
        );

        $publisher
            ->method('publish')
            ->willReturn(
                new PublicationResult(
                    '{"package":"example"}',
                    98.75,
                    $this->artifact()
                )
            );

        return $publisher;
    }

    private function artifact(): PublicationArtifact
    {
        return new PublicationArtifact(
            '/tmp/package.zip',
            'package.zip',
            'application/zip'
        );
    }

    private function storedArtifact(): StoredArtifact
    {
        return new StoredArtifact(
            '123e4567-e89b-12d3-a456-426614174000/10/package.zip',
            'package.zip',
            'application/zip',
            7,
            hash(
                'sha256',
                'package'
            )
        );
    }

    private function blueprint(): Blueprint
    {
        return new Blueprint(
            5,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-blueprint',
            'plugin',
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

    private function release(
        bool $published = false
    ): Release {
        return new Release(
            10,
            5,
            '1.0.0',
            $published ? 'published' : 'draft',
            $published ? 20 : null,
            $published,
            $published ? 98.75 : null,
            new DateTimeImmutable(
                '2026-08-03 10:00:00'
            )
        );
    }
}
