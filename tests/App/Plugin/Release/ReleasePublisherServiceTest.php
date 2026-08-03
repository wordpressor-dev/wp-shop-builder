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
use WPShop\Publisher\Contracts\PublisherInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Exception\PublisherNotFound;
use WPShop\Publisher\PublicationResult;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Exception\ReleasePublicationNotAllowed;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;

final class ReleasePublisherServiceTest extends TestCase
{
    public function testPublishesReleaseThroughResolvedPublisher(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
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
                    98.75
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
                            '{"package":"example"}',
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

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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

    public function testUnknownPublisherPreventsPersistence(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $failure = PublisherNotFound::forBlueprintType(
            'plugin'
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

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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

    public function testPublisherFailurePreventsPersistence(): void
    {
        $release = $this->release();
        $blueprint = $this->blueprint();
        $failure = new RuntimeException(
            'Publisher failed.'
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

        $publisherRegistry = $this->createMock(
            PublisherRegistryInterface::class
        );

        $publisherRegistry
            ->method('publisherFor')
            ->willReturn($publisher);

        $publicationService = $this->createMock(
            ReleasePublicationServiceInterface::class
        );

        $publicationService
            ->expects(self::never())
            ->method('publish');

        $service = new ReleasePublisherService(
            $releaseRepository,
            $blueprintRepository,
            $publicationPolicy,
            $publisherRegistry,
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
