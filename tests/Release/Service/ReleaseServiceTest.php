<?php

declare(strict_types=1);

namespace WPShop\Tests\Release\Service;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleaseNotFound;
use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;
use WPShop\Release\ReleasePage;
use WPShop\Release\ReleaseQuery;
use WPShop\Release\ReleaseUpdateData;
use WPShop\Release\Service\ReleaseService;

final class ReleaseServiceTest extends TestCase
{
    public function testCreatesReleaseThroughRepository(): void
    {
        $repository = new RecordingReleaseRepository();
        $repository->release = $this->release();

        $data = new ReleaseCreateData(
            7,
            '1.2.3'
        );

        $release = (new ReleaseService($repository))
            ->create($data);

        self::assertSame(
            $repository->release,
            $release
        );

        self::assertSame(
            $data,
            $repository->creationData
        );
    }

    public function testUpdatesReleaseThroughRepository(): void
    {
        $repository = new RecordingReleaseRepository();
        $repository->release = $this->release();

        $data = $this->updateData();

        $release = (new ReleaseService($repository))
            ->update(42, $data);

        self::assertSame(
            $repository->release,
            $release
        );

        self::assertSame(
            42,
            $repository->updatedId
        );

        self::assertSame(
            $data,
            $repository->updateData
        );
    }

    public function testThrowsWhenUpdatedReleaseIsMissing(): void
    {
        $repository = new RecordingReleaseRepository();

        $this->expectException(
            ReleaseNotFound::class
        );

        $this->expectExceptionMessage(
            'Release with identifier 42 was not found.'
        );

        (new ReleaseService($repository))->update(
            42,
            $this->updateData()
        );
    }

    public function testGetsReleaseByIdentifier(): void
    {
        $repository = new RecordingReleaseRepository();
        $repository->release = $this->release();

        $release = (new ReleaseService($repository))
            ->getById(42);

        self::assertSame(
            $repository->release,
            $release
        );

        self::assertSame(
            42,
            $repository->requestedId
        );
    }

    public function testThrowsWhenIdentifierIsMissing(): void
    {
        $repository = new RecordingReleaseRepository();

        $this->expectException(
            ReleaseNotFound::class
        );

        $this->expectExceptionMessage(
            'Release with identifier 42 was not found.'
        );

        (new ReleaseService($repository))->getById(42);
    }

    public function testGetsReleaseByBlueprintAndVersion(): void
    {
        $repository = new RecordingReleaseRepository();
        $repository->release = $this->release();

        $release = (new ReleaseService($repository))
            ->getByBlueprintAndVersion(
                7,
                '1.2.3'
            );

        self::assertSame(
            $repository->release,
            $release
        );

        self::assertSame(
            7,
            $repository->requestedBlueprintId
        );

        self::assertSame(
            '1.2.3',
            $repository->requestedVersion
        );
    }

    public function testThrowsWhenBlueprintVersionIsMissing(): void
    {
        $repository = new RecordingReleaseRepository();

        $this->expectException(
            ReleaseNotFound::class
        );

        $this->expectExceptionMessage(
            'Release for blueprint 7 with version "1.2.3" was not found.'
        );

        (new ReleaseService($repository))
            ->getByBlueprintAndVersion(
                7,
                '1.2.3'
            );
    }

    public function testGetsReleaseCollectionThroughRepository(): void
    {
        $repository = new RecordingReleaseRepository();

        $repository->releases = [
            $this->release(),
        ];

        $query = new ReleaseQuery(
            status: 'published'
        );

        $releases = (new ReleaseService($repository))
            ->getAll($query);

        self::assertSame(
            $repository->releases,
            $releases
        );

        self::assertSame(
            $query,
            $repository->collectionQuery
        );
    }

    public function testGetsReleasePageThroughRepository(): void
    {
        $repository = new RecordingReleaseRepository();

        $repository->page = new ReleasePage(
            [
                $this->release(),
            ],
            101,
            25,
            50
        );

        $query = new ReleaseQuery(
            limit: 25,
            offset: 50
        );

        $page = (new ReleaseService($repository))
            ->getPage($query);

        self::assertSame(
            $repository->page,
            $page
        );

        self::assertSame(
            $query,
            $repository->pageQuery
        );
    }

    private function updateData(): ReleaseUpdateData
    {
        return new ReleaseUpdateData(
            '2.0.0',
            'published',
            15,
            true,
            99.5
        );
    }

    private function release(): Release
    {
        return new Release(
            42,
            7,
            '1.2.3',
            'published',
            15,
            true,
            98.75,
            new DateTimeImmutable(
                '2026-08-01 12:00:00'
            )
        );
    }
}

final class RecordingReleaseRepository implements
    ReleaseRepositoryInterface
{
    public ?Release $release = null;

    public ?ReleaseCreateData $creationData = null;

    public ?int $updatedId = null;

    public ?ReleaseUpdateData $updateData = null;

    public ?int $requestedId = null;

    public ?int $requestedBlueprintId = null;

    public ?string $requestedVersion = null;

    /**
     * @var list<Release>
     */
    public array $releases = [];

    public ?ReleasePage $page = null;

    public ?ReleaseQuery $collectionQuery = null;

    public ?ReleaseQuery $pageQuery = null;

    public function create(
        ReleaseCreateData $data
    ): Release {
        $this->creationData = $data;

        return $this->release
            ?? throw new LogicException(
                'Release fixture is missing.'
            );
    }

    public function update(
        int $id,
        ReleaseUpdateData $data
    ): ?Release {
        $this->updatedId = $id;
        $this->updateData = $data;

        return $this->release;
    }

    public function findById(int $id): ?Release
    {
        $this->requestedId = $id;

        return $this->release;
    }

    public function findByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): ?Release {
        $this->requestedBlueprintId =
            $blueprintId;

        $this->requestedVersion = $version;

        return $this->release;
    }

    public function findAll(
        ReleaseQuery $query
    ): array {
        $this->collectionQuery = $query;

        return $this->releases;
    }

    public function findPage(
        ReleaseQuery $query
    ): ReleasePage {
        $this->pageQuery = $query;

        return $this->page
            ?? throw new LogicException(
                'Release page fixture is missing.'
            );
    }
}
