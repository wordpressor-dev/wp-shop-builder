<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\BlueprintPage;
use WPShop\Blueprint\BlueprintQuery;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintNotFound;
use WPShop\Blueprint\Service\BlueprintService;

final class BlueprintServiceTest extends TestCase
{
    private const UUID =
        '123e4567-e89b-12d3-a456-426614174000';

    public function testCreatesBlueprintThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $data = new BlueprintCreateData(
            'example-plugin',
            'plugin'
        );

        $created = (new BlueprintService($repository))
            ->create($data);

        self::assertSame(
            $repository->blueprint,
            $created
        );

        self::assertSame(
            $data,
            $repository->creationData
        );
    }

    public function testUpdatesBlueprintThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $data = $this->updateData();

        $updated = (new BlueprintService($repository))
            ->update(42, $data);

        self::assertSame(
            $repository->blueprint,
            $updated
        );

        self::assertSame(42, $repository->updatedId);

        self::assertSame(
            $data,
            $repository->updateData
        );
    }

    public function testThrowsWhenUpdatedBlueprintIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 42 was not found.'
        );

        (new BlueprintService($repository))
            ->update(42, $this->updateData());
    }

    public function testSoftDeletesBlueprintThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();

        (new BlueprintService($repository))
            ->delete(42);

        self::assertSame(42, $repository->deletedId);
    }

    public function testThrowsWhenDeletedBlueprintIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->deleteResult = false;

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 42 was not found.'
        );

        (new BlueprintService($repository))
            ->delete(42);
    }

    public function testRestoresBlueprintThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $restored = (new BlueprintService($repository))
            ->restore(42);

        self::assertSame(
            $repository->blueprint,
            $restored
        );

        self::assertSame(42, $repository->restoredId);
    }

    public function testThrowsWhenRestoredBlueprintIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 42 was not found.'
        );

        (new BlueprintService($repository))
            ->restore(42);
    }

    public function testGetsBlueprintByIdentifier(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getById(42);

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(42, $repository->requestedId);
    }

    public function testThrowsWhenIdentifierIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 42 was not found.'
        );

        (new BlueprintService($repository))
            ->getById(42);
    }

    public function testGetsBlueprintByUuid(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getByUuid(self::UUID);

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(
            self::UUID,
            $repository->requestedUuid
        );
    }

    public function testThrowsWhenUuidIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Blueprint with UUID "%s" was not found.',
                self::UUID
            )
        );

        (new BlueprintService($repository))
            ->getByUuid(self::UUID);
    }

    public function testGetsDeletedBlueprintByIdentifier(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getByIdIncludingDeleted(42);

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(
            42,
            $repository->requestedIncludingDeletedId
        );
    }

    public function testThrowsWhenDeletedIdentifierIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with identifier 42 was not found.'
        );

        (new BlueprintService($repository))
            ->getByIdIncludingDeleted(42);
    }

    public function testGetsDeletedBlueprintByUuid(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getByUuidIncludingDeleted(self::UUID);

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(
            self::UUID,
            $repository->requestedIncludingDeletedUuid
        );
    }

    public function testThrowsWhenDeletedUuidIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            sprintf(
                'Blueprint with UUID "%s" was not found.',
                self::UUID
            )
        );

        (new BlueprintService($repository))
            ->getByUuidIncludingDeleted(self::UUID);
    }

    public function testGetsBlueprintCollectionThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->collection = [$this->blueprint()];

        $query = new BlueprintQuery(
            type: 'plugin',
            state: 'published'
        );

        $collection = (new BlueprintService($repository))
            ->getAll($query);

        self::assertSame(
            $repository->collection,
            $collection
        );

        self::assertSame(
            $query,
            $repository->collectionQuery
        );
    }

    public function testReturnsEmptyBlueprintCollection(): void
    {
        $repository = new RecordingBlueprintRepository();
        $query = new BlueprintQuery();

        $collection = (new BlueprintService($repository))
            ->getAll($query);

        self::assertSame([], $collection);

        self::assertSame(
            $query,
            $repository->collectionQuery
        );
    }

    public function testGetsBlueprintPageThroughRepository(): void
    {
        $repository = new RecordingBlueprintRepository();

        $repository->page = new BlueprintPage(
            [$this->blueprint()],
            101,
            25,
            50
        );

        $query = new BlueprintQuery(
            type: 'plugin',
            state: 'published',
            limit: 25,
            offset: 50
        );

        $page = (new BlueprintService($repository))
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

    public function testReturnsEmptyBlueprintPage(): void
    {
        $repository = new RecordingBlueprintRepository();

        $repository->page = new BlueprintPage(
            [],
            0,
            50,
            0
        );

        $query = new BlueprintQuery();

        $page = (new BlueprintService($repository))
            ->getPage($query);

        self::assertSame(
            $repository->page,
            $page
        );

        self::assertSame(
            $query,
            $repository->pageQuery
        );

        self::assertSame(0, $page->total());
        self::assertSame(0, $page->totalPages());
    }

    public function testGetsBlueprintBySlug(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getBySlug('example-plugin');

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(
            'example-plugin',
            $repository->requestedSlug
        );
    }

    public function testThrowsWhenSlugIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with slug "missing-plugin" was not found.'
        );

        (new BlueprintService($repository))
            ->getBySlug('missing-plugin');
    }

    public function testGetsDeletedBlueprintBySlug(): void
    {
        $repository = new RecordingBlueprintRepository();
        $repository->blueprint = $this->blueprint();

        $blueprint = (new BlueprintService($repository))
            ->getBySlugIncludingDeleted(
                'example-plugin'
            );

        self::assertSame(
            $repository->blueprint,
            $blueprint
        );

        self::assertSame(
            'example-plugin',
            $repository->requestedIncludingDeletedSlug
        );
    }

    public function testThrowsWhenDeletedSlugIsMissing(): void
    {
        $repository = new RecordingBlueprintRepository();

        $this->expectException(
            BlueprintNotFound::class
        );

        $this->expectExceptionMessage(
            'Blueprint with slug "missing-plugin" was not found.'
        );

        (new BlueprintService($repository))
            ->getBySlugIncludingDeleted(
                'missing-plugin'
            );
    }

    private function updateData(): BlueprintUpdateData
    {
        return new BlueprintUpdateData(
            'updated-plugin',
            'plugin',
            null,
            null,
            null,
            'published',
            'reviewed'
        );
    }

    private function blueprint(): Blueprint
    {
        $date = new DateTimeImmutable(
            '2026-07-31 10:00:00'
        );

        return new Blueprint(
            42,
            self::UUID,
            'example-plugin',
            'plugin',
            null,
            null,
            null,
            'draft',
            'default',
            $date,
            $date,
            null
        );
    }
}

final class RecordingBlueprintRepository implements
    BlueprintRepositoryInterface
{
    public ?Blueprint $blueprint = null;

    public ?BlueprintCreateData $creationData = null;

    public ?BlueprintUpdateData $updateData = null;

    /**
     * @var list<Blueprint>
     */
    public array $collection = [];

    public ?BlueprintQuery $collectionQuery = null;

    public ?BlueprintPage $page = null;

    public ?BlueprintQuery $pageQuery = null;

    public ?int $updatedId = null;

    public ?int $deletedId = null;

    public ?int $restoredId = null;

    public bool $deleteResult = true;

    public ?int $requestedId = null;

    public ?string $requestedUuid = null;

    public ?string $requestedSlug = null;

    public ?int $requestedIncludingDeletedId = null;

    public ?string $requestedIncludingDeletedUuid = null;

    public ?string $requestedIncludingDeletedSlug = null;

    public function create(
        BlueprintCreateData $data
    ): Blueprint {
        $this->creationData = $data;

        return $this->blueprint
            ?? throw new \LogicException(
                'Blueprint fixture is missing.'
            );
    }

    public function update(
        int $id,
        BlueprintUpdateData $data
    ): ?Blueprint {
        $this->updatedId = $id;
        $this->updateData = $data;

        return $this->blueprint;
    }

    public function softDelete(int $id): bool
    {
        $this->deletedId = $id;

        return $this->deleteResult;
    }

    public function restore(int $id): ?Blueprint
    {
        $this->restoredId = $id;

        return $this->blueprint;
    }

    public function findAll(
        BlueprintQuery $query
    ): array {
        $this->collectionQuery = $query;

        return $this->collection;
    }

    public function findPage(
        BlueprintQuery $query
    ): BlueprintPage {
        $this->pageQuery = $query;

        return $this->page
            ?? throw new \LogicException(
                'Blueprint page fixture is missing.'
            );
    }

    public function findById(int $id): ?Blueprint
    {
        $this->requestedId = $id;

        return $this->blueprint;
    }

    public function findByUuid(
        string $uuid
    ): ?Blueprint {
        $this->requestedUuid = $uuid;

        return $this->blueprint;
    }

    public function findBySlug(
        string $slug
    ): ?Blueprint {
        $this->requestedSlug = $slug;

        return $this->blueprint;
    }

    public function findByIdIncludingDeleted(
        int $id
    ): ?Blueprint {
        $this->requestedIncludingDeletedId = $id;

        return $this->blueprint;
    }

    public function findByUuidIncludingDeleted(
        string $uuid
    ): ?Blueprint {
        $this->requestedIncludingDeletedUuid = $uuid;

        return $this->blueprint;
    }

    public function findBySlugIncludingDeleted(
        string $slug
    ): ?Blueprint {
        $this->requestedIncludingDeletedSlug = $slug;

        return $this->blueprint;
    }
}
