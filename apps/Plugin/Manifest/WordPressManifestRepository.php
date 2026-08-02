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
