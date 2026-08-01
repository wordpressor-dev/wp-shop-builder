<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Blueprint;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Blueprint\Blueprint;
use WPShop\Blueprint\BlueprintCreateData;
use WPShop\Blueprint\BlueprintPage;
use WPShop\Blueprint\BlueprintQuery;
use WPShop\Blueprint\BlueprintUpdateData;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Exception\BlueprintPersistenceFailed;

final readonly class WordPressBlueprintRepository implements
    BlueprintRepositoryInterface
{
    /**
     * @param Closure(): string $uuidGenerator
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private DatabaseConnectionInterface $database,
        private BlueprintRowMapper $mapper,
        private string $table,
        private Closure $uuidGenerator,
        private Closure $clock
    ) {
        if (
            preg_match(
                '/^[a-zA-Z0-9_]+$/D',
                $table
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Blueprint table name is invalid.'
            );
        }
    }

    public function create(
        BlueprintCreateData $data
    ): Blueprint {
        try {
            $uuid = ($this->uuidGenerator)();
            $now = ($this->clock)();

            $insertData = [
                'uuid' => $uuid,
                'slug' => $data->slug(),
                'type' => $data->type(),
                'state' => $data->state(),
                'workflow' => $data->workflow(),
                'created_at' => $now->format(
                    'Y-m-d H:i:s'
                ),
                'updated_at' => $now->format(
                    'Y-m-d H:i:s'
                ),
            ];

            $formats = [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ];

            if ($data->providerId() !== null) {
                $insertData['provider_id'] =
                    $data->providerId();

                $formats[] = '%d';
            }

            if ($data->developerId() !== null) {
                $insertData['developer_id'] =
                    $data->developerId();

                $formats[] = '%d';
            }

            $id = $this->database->insert(
                $this->table,
                $insertData,
                $formats
            );

            $blueprint = $this->fetch(
                'id',
                '%d',
                $id
            );

            if ($blueprint === null) {
                throw new UnexpectedValueException(
                    'Created blueprint could not be loaded.'
                );
            }

            return $blueprint;
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::creation(
                $exception
            );
        }
    }

    public function update(
        int $id,
        BlueprintUpdateData $data
    ): ?Blueprint {
        $this->assertPositiveId($id);

        try {
            $now = ($this->clock)();

            $affectedRows = $this->database->update(
                $this->table,
                [
                    'slug' => $data->slug(),
                    'type' => $data->type(),
                    'provider_id' =>
                        $data->providerId(),
                    'developer_id' =>
                        $data->developerId(),
                    'current_release_id' =>
                        $data->currentReleaseId(),
                    'state' => $data->state(),
                    'workflow' => $data->workflow(),
                    'updated_at' => $now->format(
                        'Y-m-d H:i:s'
                    ),
                ],
                [
                    'id' => $id,
                    'deleted_at' => null,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            $blueprint = $this->fetch(
                'id',
                '%d',
                $id
            );

            if (
                $affectedRows > 0
                && $blueprint === null
            ) {
                throw new UnexpectedValueException(
                    'Updated blueprint could not be loaded.'
                );
            }

            return $blueprint;
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::update(
                $id,
                $exception
            );
        }
    }

    public function softDelete(int $id): bool
    {
        $this->assertPositiveId($id);

        try {
            $now = ($this->clock)()->format(
                'Y-m-d H:i:s'
            );

            $affectedRows = $this->database->update(
                $this->table,
                [
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => $id,
                    'deleted_at' => null,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            return $affectedRows > 0;
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::deletion(
                $id,
                $exception
            );
        }
    }

    public function restore(int $id): ?Blueprint
    {
        $this->assertPositiveId($id);

        try {
            $blueprint = $this->fetch(
                'id',
                '%d',
                $id,
                true
            );

            if ($blueprint === null) {
                return null;
            }

            if ($blueprint->deletedAt() === null) {
                return $blueprint;
            }

            $now = ($this->clock)()->format(
                'Y-m-d H:i:s'
            );

            $this->database->update(
                $this->table,
                [
                    'deleted_at' => null,
                    'updated_at' => $now,
                ],
                [
                    'id' => $id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            $restored = $this->fetch(
                'id',
                '%d',
                $id
            );

            if ($restored === null) {
                throw new UnexpectedValueException(
                    'Restored blueprint could not be loaded.'
                );
            }

            return $restored;
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::restoration(
                $id,
                $exception
            );
        }
    }

    public function findById(int $id): ?Blueprint
    {
        $this->assertPositiveId($id);

        try {
            return $this->fetch(
                'id',
                '%d',
                $id
            );
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::lookup(
                'id',
                $id,
                $exception
            );
        }
    }

    public function findByUuid(
        string $uuid
    ): ?Blueprint {
        $this->assertUuid($uuid);

        try {
            return $this->fetch(
                'uuid',
                '%s',
                $uuid
            );
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::lookup(
                'uuid',
                $uuid,
                $exception
            );
        }
    }

    public function findByIdIncludingDeleted(
        int $id
    ): ?Blueprint {
        $this->assertPositiveId($id);

        try {
            return $this->fetch(
                'id',
                '%d',
                $id,
                true
            );
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::lookup(
                'id',
                $id,
                $exception
            );
        }
    }

    public function findByUuidIncludingDeleted(
        string $uuid
    ): ?Blueprint {
        $this->assertUuid($uuid);

        try {
            return $this->fetch(
                'uuid',
                '%s',
                $uuid,
                true
            );
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::lookup(
                'uuid',
                $uuid,
                $exception
            );
        }
    }

    public function findAll(
        BlueprintQuery $query
    ): array {
        try {
            return $this->fetchCollection($query);
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::collection(
                $exception
            );
        }
    }

    public function findPage(
        BlueprintQuery $query
    ): BlueprintPage {
        try {
            return new BlueprintPage(
                $this->fetchCollection($query),
                $this->countCollection($query),
                $query->limit(),
                $query->offset()
            );
        } catch (Throwable $exception) {
            throw BlueprintPersistenceFailed::collection(
                $exception
            );
        }
    }

    /**
     * @return list<Blueprint>
     */
    private function fetchCollection(
        BlueprintQuery $query
    ): array {
        $filter = $this->collectionFilter($query);
        $parameters = $filter['parameters'];

        $parameters[] = $query->limit();
        $parameters[] = $query->offset();

        $sql = sprintf(
            'SELECT id, uuid, slug, type, '
            . 'provider_id, developer_id, '
            . 'current_release_id, state, workflow, '
            . 'created_at, updated_at, deleted_at '
            . 'FROM %s '
            . 'WHERE %s '
            . 'ORDER BY %s %s '
            . 'LIMIT %%d OFFSET %%d',
            $this->table,
            implode(
                ' AND ',
                $filter['conditions']
            ),
            $this->sortColumn($query->sortBy()),
            strtoupper($query->sortDirection())
        );

        $rows = $this->database->fetchAll(
            $sql,
            $parameters
        );

        $blueprints = [];

        foreach ($rows as $row) {
            $blueprints[] = $this->mapper->map($row);
        }

        return $blueprints;
    }

    private function countCollection(
        BlueprintQuery $query
    ): int {
        $filter = $this->collectionFilter($query);

        $sql = sprintf(
            'SELECT COUNT(*) '
            . 'FROM %s '
            . 'WHERE %s',
            $this->table,
            implode(
                ' AND ',
                $filter['conditions']
            )
        );

        return $this->database->fetchInteger(
            $sql,
            $filter['parameters']
        );
    }

    /**
     * @return array{
     *     conditions: list<string>,
     *     parameters: list<int|float|string>
     * }
     */
    private function collectionFilter(
        BlueprintQuery $query
    ): array {
        $conditions = ['1 = 1'];
        $parameters = [];

        if (! $query->includingDeleted()) {
            $conditions[] = 'deleted_at IS NULL';
        }

        if ($query->type() !== null) {
            $conditions[] = 'type = %s';
            $parameters[] = $query->type();
        }

        if ($query->state() !== null) {
            $conditions[] = 'state = %s';
            $parameters[] = $query->state();
        }

        if ($query->workflow() !== null) {
            $conditions[] = 'workflow = %s';
            $parameters[] = $query->workflow();
        }

        return [
            'conditions' => $conditions,
            'parameters' => $parameters,
        ];
    }

    private function fetch(
        string $field,
        string $placeholder,
        int|string $value,
        bool $includingDeleted = false
    ): ?Blueprint {
        $deletedCondition = $includingDeleted
            ? ''
            : 'AND deleted_at IS NULL ';

        $sql = sprintf(
            'SELECT id, uuid, slug, type, '
            . 'provider_id, developer_id, '
            . 'current_release_id, state, workflow, '
            . 'created_at, updated_at, deleted_at '
            . 'FROM %s '
            . 'WHERE %s = %s '
            . '%s'
            . 'LIMIT 1',
            $this->table,
            $field,
            $placeholder,
            $deletedCondition
        );

        $row = $this->database->fetchOne(
            $sql,
            [$value]
        );

        if ($row === null) {
            return null;
        }

        return $this->mapper->map($row);
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            BlueprintQuery::SORT_ID => 'id',
            BlueprintQuery::SORT_SLUG => 'slug',
            BlueprintQuery::SORT_TYPE => 'type',
            BlueprintQuery::SORT_STATE => 'state',
            BlueprintQuery::SORT_WORKFLOW => 'workflow',
            BlueprintQuery::SORT_CREATED_AT => 'created_at',
            BlueprintQuery::SORT_UPDATED_AT => 'updated_at',
            default => throw new UnexpectedValueException(
                'Blueprint collection sort field is invalid.'
            ),
        };
    }

    private function assertPositiveId(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException(
                'Blueprint identifier must be positive.'
            );
        }
    }

    private function assertUuid(string $uuid): void
    {
        if (
            preg_match(
                '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}'
                . '[0-9a-f]{12}$/Di',
                $uuid
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Blueprint UUID is invalid.'
            );
        }
    }
}
