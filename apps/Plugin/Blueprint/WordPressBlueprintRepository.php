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
