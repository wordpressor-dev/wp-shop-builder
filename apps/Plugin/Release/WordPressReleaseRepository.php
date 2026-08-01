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
            throw ReleasePersistenceFailed::blueprintVersionLookup(
                $blueprintId,
                $version,
                $exception
            );
        }
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
