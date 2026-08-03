<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Release;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Release\ReleasePublicationService;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Exception\ManifestNotFound;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestUpdateData;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Release;
use WPShop\Release\ReleasePublicationData;
use WPShop\Release\ReleaseUpdateData;

final class ReleasePublicationServiceTest extends TestCase
{
    public function testPublishesReleaseWithNewManifestInsideTransaction(): void
    {
        $manifestJson = '{"name":"example-plugin","version":"1.2.3"}';

        $release = $this->release();
        $blueprint = $this->blueprint();

        $manifest = new Manifest(
            15,
            42,
            $manifestJson,
            hash('sha256', $manifestJson),
            new DateTimeImmutable('2026-08-03 12:00:00')
        );

        $publishedRelease = $this->release(
            status: 'published',
            manifestId: 15,
            published: true,
            validationScore: 98.75
        );

        $updatedBlueprint = $this->blueprint(
            currentReleaseId: 42
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($release);

        $releaseRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                42,
                self::callback(
                    static function (
                        ReleaseUpdateData $data
                    ): bool {
                        self::assertSame(
                            '1.2.3',
                            $data->version()
                        );

                        self::assertSame(
                            'published',
                            $data->status()
                        );

                        self::assertSame(
                            15,
                            $data->manifestId()
                        );

                        self::assertTrue(
                            $data->published()
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

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($blueprint);

        $blueprintRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                7,
                self::callback(
                    static function (
                        BlueprintUpdateData $data
                    ): bool {
                        self::assertSame(
                            'example-plugin',
                            $data->slug()
                        );

                        self::assertSame(
                            'plugin',
                            $data->type()
                        );

                        self::assertSame(
                            3,
                            $data->providerId()
                        );

                        self::assertSame(
                            4,
                            $data->developerId()
                        );

                        self::assertSame(
                            42,
                            $data->currentReleaseId()
                        );

                        self::assertSame(
                            'draft',
                            $data->state()
                        );

                        self::assertSame(
                            'reviewed',
                            $data->workflow()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($updatedBlueprint);

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn(null);

        $manifestRepository
            ->expects(self::once())
            ->method('create')
            ->with(
                self::callback(
                    static function (
                        ManifestCreateData $data
                    ) use ($manifestJson): bool {
                        self::assertSame(
                            42,
                            $data->releaseId()
                        );

                        self::assertSame(
                            $manifestJson,
                            $data->manifestJson()
                        );

                        self::assertSame(
                            hash('sha256', $manifestJson),
                            $data->manifestHash()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($manifest);

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed => $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $actual = $service->publish(
            new ReleasePublicationData(
                42,
                $manifestJson,
                98.75
            )
        );

        self::assertSame(
            $publishedRelease,
            $actual
        );
    }

    public function testPublishesReleaseWithUpdatedManifestInsideTransaction(): void
    {
        $oldManifestJson = '{"name":"old-plugin"}';
        $manifestJson = '{"name":"updated-plugin"}';

        $release = $this->release(
            manifestId: 15
        );

        $blueprint = $this->blueprint(
            currentReleaseId: 9
        );

        $existingManifest = new Manifest(
            15,
            42,
            $oldManifestJson,
            hash('sha256', $oldManifestJson),
            new DateTimeImmutable('2026-08-03 11:00:00')
        );

        $updatedManifest = new Manifest(
            15,
            42,
            $manifestJson,
            hash('sha256', $manifestJson),
            new DateTimeImmutable('2026-08-03 12:00:00')
        );

        $publishedRelease = $this->release(
            status: 'published',
            manifestId: 15,
            published: true
        );

        $updatedBlueprint = $this->blueprint(
            currentReleaseId: 42
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($release);

        $releaseRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                42,
                self::callback(
                    static function (
                        ReleaseUpdateData $data
                    ): bool {
                        self::assertSame(
                            'published',
                            $data->status()
                        );

                        self::assertSame(
                            15,
                            $data->manifestId()
                        );

                        self::assertTrue(
                            $data->published()
                        );

                        self::assertNull(
                            $data->validationScore()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($publishedRelease);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($blueprint);

        $blueprintRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                7,
                self::callback(
                    static function (
                        BlueprintUpdateData $data
                    ): bool {
                        self::assertSame(
                            42,
                            $data->currentReleaseId()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($updatedBlueprint);

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn($existingManifest);

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                15,
                self::callback(
                    static function (
                        ManifestUpdateData $data
                    ) use ($manifestJson): bool {
                        self::assertSame(
                            $manifestJson,
                            $data->manifestJson()
                        );

                        self::assertSame(
                            hash('sha256', $manifestJson),
                            $data->manifestHash()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($updatedManifest);

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $actual = $service->publish(
            new ReleasePublicationData(
                42,
                $manifestJson,
                null
            )
        );

        self::assertSame(
            $publishedRelease,
            $actual
        );
    }
    public function testMissingReleasePreventsPublicationWrites(): void
    {
        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn(null);

        $releaseRepository
            ->expects(self::never())
            ->method('update');

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::never())
            ->method('findById');

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::never())
            ->method('findByReleaseId');

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $this->expectException(
            ReleaseNotFound::class
        );

        $service->publish(
            new ReleasePublicationData(
                42,
                '{"name":"example-plugin"}',
                null
            )
        );
    }
    public function testMissingBlueprintPreventsPublicationWrites(): void
    {
        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->release());

        $releaseRepository
            ->expects(self::never())
            ->method('update');

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn(null);

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::never())
            ->method('findByReleaseId');

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $this->expectException(
            BlueprintNotFound::class
        );

        $service->publish(
            new ReleasePublicationData(
                42,
                '{"name":"example-plugin"}',
                null
            )
        );
    }
    public function testReleaseUpdateFailurePropagatesAndPreventsBlueprintUpdate(): void
    {
        $manifestJson = '{"name":"example-plugin"}';

        $manifest = new Manifest(
            15,
            42,
            $manifestJson,
            hash('sha256', $manifestJson),
            new DateTimeImmutable('2026-08-03 12:00:00')
        );

        $failure = new RuntimeException(
            'Release update failed.'
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->release());

        $releaseRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                42,
                self::isInstanceOf(
                    ReleaseUpdateData::class
                )
            )
            ->willThrowException($failure);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->blueprint());

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn(null);

        $manifestRepository
            ->expects(self::once())
            ->method('create')
            ->willReturn($manifest);

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        try {
            $service->publish(
                new ReleasePublicationData(
                    42,
                    $manifestJson,
                    null
                )
            );

            self::fail(
                'Release update failure was not propagated.'
            );
        } catch (RuntimeException $actual) {
            self::assertSame(
                $failure,
                $actual
            );
        }
    }
    public function testManifestUpdateFailurePropagatesAndPreventsPublicationWrites(): void
    {
        $oldManifestJson = '{"name":"old-plugin"}';
        $manifestJson = '{"name":"updated-plugin"}';

        $existingManifest = new Manifest(
            15,
            42,
            $oldManifestJson,
            hash('sha256', $oldManifestJson),
            new DateTimeImmutable('2026-08-03 11:00:00')
        );

        $failure = new RuntimeException(
            'Manifest update failed.'
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn(
                $this->release(
                    manifestId: 15
                )
            );

        $releaseRepository
            ->expects(self::never())
            ->method('update');

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->blueprint());

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn($existingManifest);

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                15,
                self::isInstanceOf(
                    ManifestUpdateData::class
                )
            )
            ->willThrowException($failure);

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        try {
            $service->publish(
                new ReleasePublicationData(
                    42,
                    $manifestJson,
                    null
                )
            );

            self::fail(
                'Manifest update failure was not propagated.'
            );
        } catch (RuntimeException $actual) {
            self::assertSame(
                $failure,
                $actual
            );
        }
    }
    public function testMissingBlueprintAfterUpdateTriggersPublicationFailure(): void
    {
        $manifestJson = '{"name":"example-plugin"}';

        $manifest = new Manifest(
            15,
            42,
            $manifestJson,
            hash('sha256', $manifestJson),
            new DateTimeImmutable('2026-08-03 12:00:00')
        );

        $publishedRelease = $this->release(
            status: 'published',
            manifestId: 15,
            published: true,
            validationScore: 98.75
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->release());

        $releaseRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                42,
                self::isInstanceOf(
                    ReleaseUpdateData::class
                )
            )
            ->willReturn($publishedRelease);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->blueprint());

        $blueprintRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                7,
                self::isInstanceOf(
                    BlueprintUpdateData::class
                )
            )
            ->willReturn(null);

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn(null);

        $manifestRepository
            ->expects(self::once())
            ->method('create')
            ->willReturn($manifest);

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $this->expectException(
            BlueprintNotFound::class
        );

        $service->publish(
            new ReleasePublicationData(
                42,
                $manifestJson,
                98.75
            )
        );
    }
    public function testMissingReleaseAfterUpdatePreventsBlueprintUpdate(): void
    {
        $manifestJson = '{"name":"example-plugin"}';

        $manifest = new Manifest(
            15,
            42,
            $manifestJson,
            hash('sha256', $manifestJson),
            new DateTimeImmutable('2026-08-03 12:00:00')
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn($this->release());

        $releaseRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                42,
                self::isInstanceOf(
                    ReleaseUpdateData::class
                )
            )
            ->willReturn(null);

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->blueprint());

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn(null);

        $manifestRepository
            ->expects(self::once())
            ->method('create')
            ->willReturn($manifest);

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $this->expectException(
            ReleaseNotFound::class
        );

        $service->publish(
            new ReleasePublicationData(
                42,
                $manifestJson,
                null
            )
        );
    }
    public function testMissingManifestAfterUpdatePreventsPublicationWrites(): void
    {
        $oldManifestJson = '{"name":"old-plugin"}';
        $manifestJson = '{"name":"updated-plugin"}';

        $existingManifest = new Manifest(
            15,
            42,
            $oldManifestJson,
            hash('sha256', $oldManifestJson),
            new DateTimeImmutable('2026-08-03 11:00:00')
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn(
                $this->release(
                    manifestId: 15
                )
            );

        $releaseRepository
            ->expects(self::never())
            ->method('update');

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($this->blueprint());

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::once())
            ->method('findByReleaseId')
            ->with(42)
            ->willReturn($existingManifest);

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::once())
            ->method('update')
            ->with(
                15,
                self::isInstanceOf(
                    ManifestUpdateData::class
                )
            )
            ->willReturn(null);

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(
                static fn(callable $operation): mixed =>
                    $operation()
            );

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        $this->expectException(
            ManifestNotFound::class
        );

        $service->publish(
            new ReleasePublicationData(
                42,
                $manifestJson,
                null
            )
        );
    }
    public function testTransactionFailurePreventsPublicationOperations(): void
    {
        $failure = new RuntimeException(
            'Transaction failed.'
        );

        $releaseRepository = $this->createMock(
            ReleaseRepositoryInterface::class
        );

        $releaseRepository
            ->expects(self::never())
            ->method('findById');

        $releaseRepository
            ->expects(self::never())
            ->method('update');

        $blueprintRepository = $this->createMock(
            BlueprintRepositoryInterface::class
        );

        $blueprintRepository
            ->expects(self::never())
            ->method('findById');

        $blueprintRepository
            ->expects(self::never())
            ->method('update');

        $manifestRepository = $this->createMock(
            ManifestRepositoryInterface::class
        );

        $manifestRepository
            ->expects(self::never())
            ->method('findByReleaseId');

        $manifestRepository
            ->expects(self::never())
            ->method('create');

        $manifestRepository
            ->expects(self::never())
            ->method('update');

        $transactionManager = $this->createMock(
            TransactionManagerInterface::class
        );

        $transactionManager
            ->expects(self::once())
            ->method('transactional')
            ->willThrowException($failure);

        $service = new ReleasePublicationService(
            $releaseRepository,
            $blueprintRepository,
            $manifestRepository,
            $transactionManager
        );

        try {
            $service->publish(
                new ReleasePublicationData(
                    42,
                    '{"name":"example-plugin"}',
                    null
                )
            );

            self::fail(
                'Transaction failure was not propagated.'
            );
        } catch (RuntimeException $actual) {
            self::assertSame(
                $failure,
                $actual
            );
        }
    }
    private function release(
        string $status = 'reviewed',
        ?int $manifestId = null,
        bool $published = false,
        ?float $validationScore = null
    ): Release {
        return new Release(
            42,
            7,
            '1.2.3',
            $status,
            $manifestId,
            $published,
            $validationScore,
            new DateTimeImmutable('2026-08-03 10:00:00')
        );
    }

    private function blueprint(
        ?int $currentReleaseId = null
    ): Blueprint {
        return new Blueprint(
            7,
            '123e4567-e89b-12d3-a456-426614174000',
            'example-plugin',
            'plugin',
            3,
            4,
            $currentReleaseId,
            'draft',
            'reviewed',
            new DateTimeImmutable('2026-08-01 10:00:00'),
            new DateTimeImmutable('2026-08-02 10:00:00'),
            null
        );
    }
}
