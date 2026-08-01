<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Release;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Exception\ReleasePersistenceFailed;
use WPShop\Release\Release;
use WPShop\Release\ReleaseCreateData;
use WPShop\Release\ReleasePage;
use WPShop\Release\ReleaseQuery;
use WPShop\Release\ReleaseUpdateData;

final readonly class WordPressReleaseRepository implements
    ReleaseRepositoryInterface
{
    /**
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private DatabaseConnectionInterface $database,
        private ReleaseRowMapper $mapper,
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
                'Release table name is invalid.'
            );
        }
    }

    public function create(
        ReleaseCreateData $data
    ): Release {
        try {
            $createdAt = ($this->clock)();

            $insertData = [
                'blueprint_id' => $data->blueprintId(),
                'version' => $data->version(),
                'status' => $data->status(),
                'published' => $data->published()
                    ? 1
                    : 0,
                'created_at' => $createdAt->format(
                    'Y-m-d H:i:s'
                ),
            ];

            $formats = [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            ];

            if ($data->manifestId() !== null) {
                $insertData['manifest_id'] =
                    $data->manifestId();

                $formats[] = '%d';
            }

            if ($data->validationScore() !== null) {
                $insertData['validation_score'] =
                    $data->validationScore();

                $formats[] = '%f';
            }

            $id = $this->database->insert(
                $this->table,
                $insertData,
                $formats
            );

            $release = $this->fetchById($id);

            if ($release === null) {
                throw new UnexpectedValueException(
                    'Created release could not be loaded.'
                );
            }

            return $release;
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed::creation(
                $exception
            );
        }
    }

    public function update(
        int $id,
        ReleaseUpdateData $data
    ): ?Release {
        $this->assertPositiveId(
            $id,
            'identifier'
        );

        try {
            $affectedRows = $this->database->update(
                $this->table,
                [
                    'version' => $data->version(),
                    'status' => $data->status(),
                    'manifest_id' => $data->manifestId(),
                    'published' => $data->published()
                        ? 1
                        : 0,
                    'validation_score' =>
                        $data->validationScore(),
                ],
                [
                    'id' => $id,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%f',
                ],
                [
                    '%d',
                ]
            );

            $release = $this->fetchById($id);

            if (
                $affectedRows > 0
                && $release === null
            ) {
                throw new UnexpectedValueException(
                    'Updated release could not be loaded.'
                );
            }

            return $release;
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed::update(
                $id,
                $exception
            );
        }
    }

    public function findById(int $id): ?Release
    {
        $this->assertPositiveId(
            $id,
            'identifier'
        );

        try {
            return $this->fetchById($id);
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed::lookup(
                'id',
                $id,
                $exception
            );
        }
    }

    public function findByBlueprintAndVersion(
        int $blueprintId,
        string $version
    ): ?Release {
        $this->assertPositiveId(
            $blueprintId,
            'blueprint identifier'
        );

        $this->assertText(
            $version,
            'version',
            64
        );

        try {
            $sql = sprintf(
                'SELECT id, blueprint_id, version, status, '
                . 'manifest_id, published, validation_score, '
                . 'created_at '
                . 'FROM %s '
                . 'WHERE blueprint_id = %%d '
                . 'AND version = %%s '
                . 'LIMIT 1',
                $this->table
            );

            $row = $this->database->fetchOne(
                $sql,
                [
                    $blueprintId,
                    $version,
                ]
            );

            if ($row === null) {
                return null;
            }

            return $this->mapper->map($row);
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed
                ::blueprintVersionLookup(
                    $blueprintId,
                    $version,
                    $exception
                );
        }
    }

    public function findAll(
        ReleaseQuery $query
    ): array {
        try {
            return $this->fetchCollection($query);
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed::collection(
                $exception
            );
        }
    }

    public function findPage(
        ReleaseQuery $query
    ): ReleasePage {
        try {
            return new ReleasePage(
                $this->fetchCollection($query),
                $this->countCollection($query),
                $query->limit(),
                $query->offset()
            );
        } catch (Throwable $exception) {
            throw ReleasePersistenceFailed::collection(
                $exception
            );
        }
    }

    /**
     * @return list<Release>
     */
    private function fetchCollection(
        ReleaseQuery $query
    ): array {
        $filter = $this->collectionFilter($query);
        $parameters = $filter['parameters'];

        $parameters[] = $query->limit();
        $parameters[] = $query->offset();

        $sql = sprintf(
            'SELECT id, blueprint_id, version, status, '
            . 'manifest_id, published, validation_score, '
            . 'created_at '
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

        $releases = [];

        foreach ($rows as $row) {
            $releases[] = $this->mapper->map($row);
        }

        return $releases;
    }

    private function countCollection(
        ReleaseQuery $query
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
        ReleaseQuery $query
    ): array {
        $conditions = ['1 = 1'];
        $parameters = [];

        if ($query->blueprintId() !== null) {
            $conditions[] = 'blueprint_id = %d';
            $parameters[] = $query->blueprintId();
        }

        if ($query->status() !== null) {
            $conditions[] = 'status = %s';
            $parameters[] = $query->status();
        }

        $published = $query->published();

        if ($published !== null) {
            $conditions[] = 'published = %d';
            $parameters[] = $published
                ? 1
                : 0;
        }

        return [
            'conditions' => $conditions,
            'parameters' => $parameters,
        ];
    }

    private function fetchById(int $id): ?Release
    {
        $sql = sprintf(
            'SELECT id, blueprint_id, version, status, '
            . 'manifest_id, published, validation_score, '
            . 'created_at '
            . 'FROM %s '
            . 'WHERE id = %%d '
            . 'LIMIT 1',
            $this->table
        );

        $row = $this->database->fetchOne(
            $sql,
            [$id]
        );

        if ($row === null) {
            return null;
        }

        return $this->mapper->map($row);
    }

    private function sortColumn(string $sortBy): string
    {
        return match ($sortBy) {
            ReleaseQuery::SORT_ID => 'id',
            ReleaseQuery::SORT_BLUEPRINT_ID =>
                'blueprint_id',
            ReleaseQuery::SORT_VERSION => 'version',
            ReleaseQuery::SORT_STATUS => 'status',
            ReleaseQuery::SORT_PUBLISHED =>
                'published',
            ReleaseQuery::SORT_VALIDATION_SCORE =>
                'validation_score',
            ReleaseQuery::SORT_CREATED_AT =>
                'created_at',
            default => throw new UnexpectedValueException(
                'Release collection sort field is invalid.'
            ),
        };
    }

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id < 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release %s must be positive.',
                    $field
                )
            );
        }
    }

    private function assertText(
        string $value,
        string $field,
        int $maximumLength
    ): void {
        if (
            trim($value) === ''
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Release %s must contain between 1 and %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }
    }
}
