<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Manifest;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Exception\ManifestPersistenceFailed;
use WPShop\Manifest\Manifest;
use WPShop\Manifest\ManifestCreateData;
use WPShop\Manifest\ManifestPage;
use WPShop\Manifest\ManifestQuery;
use WPShop\Manifest\ManifestUpdateData;

final readonly class WordPressManifestRepository implements
    ManifestRepositoryInterface
{
    /**
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private DatabaseConnectionInterface $database,
        private ManifestRowMapper $mapper,
        private string $table,
        private Closure $clock
    ) {
        if (
            preg_match(
                '/^[a-zA-Z0-9_]+$/D',
                $table
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Manifest table name is invalid.'
            );
        }
    }

    public function create(
        ManifestCreateData $data
    ): Manifest {
        try {
            $createdAt = ($this->clock)();

            $id = $this->database->insert(
                $this->table,
                [
                    'release_id' =>
                        $data->releaseId(),
                    'manifest_json' =>
                        $data->manifestJson(),
                    'manifest_hash' =>
                        $data->manifestHash(),
                    'created_at' =>
                        $createdAt->format(
                            'Y-m-d H:i:s'
                        ),
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

            $manifest = $this->fetch(
                'id',
                '%d',
                $id
            );

            if (!$manifest instanceof \WPShop\Manifest\Manifest) {
                throw new UnexpectedValueException(
                    'Created manifest could not be loaded.'
                );
            }

            return $manifest;
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::creation(
                $exception
            );
        }
    }

    public function update(
        int $id,
        ManifestUpdateData $data
    ): ?Manifest {
        $this->assertPositiveId(
            $id,
            'identifier'
        );

        try {
            $affectedRows = $this->database->update(
                $this->table,
                [
                    'manifest_json' =>
                        $data->manifestJson(),
                    'manifest_hash' =>
                        $data->manifestHash(),
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

            $manifest = $this->fetch(
                'id',
                '%d',
                $id
            );

            if (
                $affectedRows > 0
                && !$manifest instanceof \WPShop\Manifest\Manifest
            ) {
                throw new UnexpectedValueException(
                    'Updated manifest could not be loaded.'
                );
            }

            return $manifest;
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::update(
                $id,
                $exception
            );
        }
    }

    public function findById(
        int $id
    ): ?Manifest {
        $this->assertPositiveId($id, 'identifier');

        try {
            return $this->fetch(
                'id',
                '%d',
                $id
            );
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::lookup(
                'id',
                $id,
                $exception
            );
        }
    }

    public function findByReleaseId(
        int $releaseId
    ): ?Manifest {
        $this->assertPositiveId($releaseId, 'release identifier');

        try {
            return $this->fetch(
                'release_id',
                '%d',
                $releaseId
            );
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::lookup(
                'release_id',
                $releaseId,
                $exception
            );
        }
    }

    /**
     * @return list<Manifest>
     */
    public function findAll(
        ManifestQuery $query
    ): array {
        try {
            return $this->fetchCollection($query);
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::collection(
                $exception
            );
        }
    }

    public function findPage(
        ManifestQuery $query
    ): ManifestPage {
        try {
            return new ManifestPage(
                $this->fetchCollection($query),
                $this->countCollection($query),
                $query->limit(),
                $query->offset()
            );
        } catch (Throwable $exception) {
            throw ManifestPersistenceFailed::collection(
                $exception
            );
        }
    }

    /**
     * @return list<Manifest>
     */
    private function fetchCollection(
        ManifestQuery $query
    ): array {
        $filter = $this->collectionFilter($query);
        $parameters = $filter['parameters'];

        $parameters[] = $query->limit();
        $parameters[] = $query->offset();

        $sql = sprintf(
            'SELECT id, release_id, manifest_json, '
            . 'manifest_hash, created_at '
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

        $manifests = [];

        foreach ($rows as $row) {
            $manifests[] = $this->mapper->map($row);
        }

        return $manifests;
    }

    private function countCollection(
        ManifestQuery $query
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
        ManifestQuery $query
    ): array {
        $conditions = ['1 = 1'];
        $parameters = [];

        if ($query->releaseId() !== null) {
            $conditions[] = 'release_id = %d';
            $parameters[] = $query->releaseId();
        }

        if ($query->manifestHash() !== null) {
            $conditions[] = 'manifest_hash = %s';
            $parameters[] = $query->manifestHash();
        }

        return [
            'conditions' => $conditions,
            'parameters' => $parameters,
        ];
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            ManifestQuery::SORT_ID => 'id',
            ManifestQuery::SORT_RELEASE_ID =>
                'release_id',
            ManifestQuery::SORT_MANIFEST_HASH =>
                'manifest_hash',
            ManifestQuery::SORT_CREATED_AT =>
                'created_at',
            default => throw new UnexpectedValueException(
                'Manifest collection sort field is invalid.'
            ),
        };
    }

    private function fetch(
        string $field,
        string $placeholder,
        int $value
    ): ?Manifest {
        $sql = sprintf(
            'SELECT id, release_id, manifest_json, '
            . 'manifest_hash, created_at '
            . 'FROM %s '
            . 'WHERE %s = %s '
            . 'LIMIT 1',
            $this->table,
            $field,
            $placeholder
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

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Manifest %s must be positive.',
                    $field
                )
            );
        }
    }
}
