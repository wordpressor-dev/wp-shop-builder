<?php

declare(strict_types=1);

namespace WPShop\Tests\Manifest\Service;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Exception\ManifestNotFound;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestPage;
use WPShop\Manifest\ManifestQuery;
use WPShop\Manifest\ManifestUpdateData;
use WPShop\Manifest\Service\ManifestService;

final class ManifestServiceTest extends TestCase
{
    private const string MANIFEST_JSON =
        '{"name":"example-plugin"}';

    public function testCreatesManifestThroughRepository(): void
    {
        $repository = new RecordingManifestRepository();
        $repository->manifest = $this->manifest();

        $data = new ManifestCreateData(
            7,
            self::MANIFEST_JSON
        );

        $manifest = (new ManifestService($repository))
            ->create($data);

        self::assertSame(
            $repository->manifest,
            $manifest
        );

        self::assertSame(
            $data,
            $repository->creationData
        );
    }

    public function testUpdatesManifestThroughRepository(): void
    {
        $repository = new RecordingManifestRepository();
        $repository->manifest = $this->manifest();

        $data = new ManifestUpdateData(
            '{"name":"example-plugin","version":"2.0.0"}'
        );

        $manifest = (new ManifestService($repository))
            ->update(
                42,
                $data
            );

        self::assertSame(
            $repository->manifest,
            $manifest
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

    public function testThrowsWhenUpdatedManifestIsMissing(): void
    {
        $repository = new RecordingManifestRepository();

        $this->expectException(
            ManifestNotFound::class
        );

        $this->expectExceptionMessage(
            'Manifest with identifier 42 was not found.'
        );

        (new ManifestService($repository))->update(
            42,
            new ManifestUpdateData('{}')
        );
    }

    public function testGetsManifestByIdentifier(): void
    {
        $repository = new RecordingManifestRepository();
        $repository->manifest = $this->manifest();

        $manifest = (new ManifestService($repository))
            ->getById(42);

        self::assertSame(
            $repository->manifest,
            $manifest
        );

        self::assertSame(
            42,
            $repository->requestedId
        );
    }

    public function testThrowsWhenIdentifierIsMissing(): void
    {
        $repository = new RecordingManifestRepository();

        $this->expectException(
            ManifestNotFound::class
        );

        $this->expectExceptionMessage(
            'Manifest with identifier 42 was not found.'
        );

        (new ManifestService($repository))
            ->getById(42);
    }

    public function testGetsManifestByReleaseIdentifier(): void
    {
        $repository = new RecordingManifestRepository();
        $repository->manifest = $this->manifest();

        $manifest = (new ManifestService($repository))
            ->getByReleaseId(7);

        self::assertSame(
            $repository->manifest,
            $manifest
        );

        self::assertSame(
            7,
            $repository->requestedReleaseId
        );
    }

    public function testThrowsWhenReleaseIsMissing(): void
    {
        $repository = new RecordingManifestRepository();

        $this->expectException(
            ManifestNotFound::class
        );

        $this->expectExceptionMessage(
            'Manifest for release 7 was not found.'
        );

        (new ManifestService($repository))
            ->getByReleaseId(7);
    }

    public function testGetsManifestCollectionThroughRepository(): void
    {
        $repository = new RecordingManifestRepository();

        $repository->manifests = [
            $this->manifest(),
        ];

        $query = new ManifestQuery(
            releaseId: 7
        );

        $manifests = (new ManifestService($repository))
            ->getAll($query);

        self::assertSame(
            $repository->manifests,
            $manifests
        );

        self::assertSame(
            $query,
            $repository->collectionQuery
        );
    }

    public function testGetsManifestPageThroughRepository(): void
    {
        $repository = new RecordingManifestRepository();

        $repository->page = new ManifestPage(
            [
                $this->manifest(),
            ],
            101,
            25,
            50
        );

        $query = new ManifestQuery(
            limit: 25,
            offset: 50
        );

        $page = (new ManifestService($repository))
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
    private function manifest(): Manifest
    {
        return new Manifest(
            42,
            7,
            self::MANIFEST_JSON,
            hash(
                'sha256',
                self::MANIFEST_JSON
            ),
            new DateTimeImmutable(
                '2026-08-02 13:00:00'
            )
        );
    }
}

final class RecordingManifestRepository implements
    ManifestRepositoryInterface
{
    public ?Manifest $manifest = null;

    public ?ManifestCreateData $creationData = null;

    public ?int $updatedId = null;

    public ?ManifestUpdateData $updateData = null;

    public ?int $requestedId = null;

    public ?int $requestedReleaseId = null;

    /**
     * @var list<Manifest>
     */
    public array $manifests = [];

    public ?ManifestPage $page = null;

    public ?ManifestQuery $collectionQuery = null;

    public ?ManifestQuery $pageQuery = null;

    public function create(
        ManifestCreateData $data
    ): Manifest {
        $this->creationData = $data;

        return $this->manifest
            ?? throw new LogicException(
                'Manifest fixture is missing.'
            );
    }

    public function update(
        int $id,
        ManifestUpdateData $data
    ): ?Manifest {
        $this->updatedId = $id;
        $this->updateData = $data;

        return $this->manifest;
    }

    public function findById(
        int $id
    ): ?Manifest {
        $this->requestedId = $id;

        return $this->manifest;
    }

    public function findByReleaseId(
        int $releaseId
    ): ?Manifest {
        $this->requestedReleaseId = $releaseId;

        return $this->manifest;
    }

    /**
     * @return list<Manifest>
     */
    public function findAll(
        ManifestQuery $query
    ): array {
        $this->collectionQuery = $query;

        return $this->manifests;
    }

    public function findPage(
        ManifestQuery $query
    ): ManifestPage {
        $this->pageQuery = $query;

        return $this->page
            ?? throw new LogicException(
                'Manifest page fixture is missing.'
            );
    }
}
