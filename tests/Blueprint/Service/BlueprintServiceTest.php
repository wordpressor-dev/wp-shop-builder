<?php

declare(strict_types=1);

namespace WPShop\Tests\Blueprint\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
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

    public ?int $updatedId = null;

    public ?int $deletedId = null;

    public bool $deleteResult = true;

    public ?int $requestedId = null;

    public ?string $requestedUuid = null;

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
}
