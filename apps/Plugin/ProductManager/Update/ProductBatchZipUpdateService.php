<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductBatchZipUpdateService
{
    public const MAX_BATCH = 10;

    public function __construct(
        private readonly ProductVersionUpdater $updater,
        private readonly ProductArchiveUpdateCoordinator $coordinator
    ) {
    }

    /**
     * @param array<int|string, mixed> $queueRows
     * @param list<int|string> $selectedIds
     * @param array<int, array<string, mixed>> $archiveFiles
     * @return array<int, array<string, mixed>>
     */
    public function preflight(
        array $queueRows,
        array $selectedIds,
        array $archiveFiles
    ): array {
        $ids = $this->selectedIds($selectedIds);
        $results = [];

        foreach ($ids as $productId) {
            $queueRow = $this->queueRow($queueRows, $productId);
            $title = $this->title($queueRow, $productId);
            $file = $archiveFiles[$productId] ?? [];

            if (! $this->validUpload($file)) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    ['BATCH PREFLIGHT = STOP', 'ZIP FILE = REQUIRED']
                );

                continue;
            }

            try {
                $data = $this->data($productId, $queueRow);
                $snapshot = $this->updater->load($productId);
                $result = $this->coordinator->preflight($data, $file);
                $logs = $result->logs;
            } catch (Throwable $exception) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    [
                        'BATCH PREFLIGHT = STOP',
                        'ERROR MESSAGE = ' . $exception->getMessage(),
                    ]
                );

                continue;
            }

            if (! $result->success) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    $logs
                );

                continue;
            }

            $zipVersion = $this->zipVersion($logs);
            $productType = CatalogProductType::infer(
                $snapshot->baseTitle,
                $snapshot->salesPage
            );

            if (
                $productType !== CatalogProductType::TEMPLATE_KIT
                && $snapshot->version !== ''
                && (
                    $zipVersion === ''
                    || version_compare($zipVersion, $snapshot->version, '<=')
                )
            ) {
                $logs[] = 'BATCH PREFLIGHT = STOP';
                $logs[] = 'ZIP VERSION MUST BE NEWER THAN CURRENT VERSION';

                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    $logs
                );

                continue;
            }

            $hash = $this->fileHash($file);

            if ($hash === '') {
                $logs[] = 'BATCH PREFLIGHT = STOP';
                $logs[] = 'ZIP SHA256 = FAILED';

                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    $logs
                );

                continue;
            }

            $logs[] = 'BATCH PREFLIGHT = READY';
            $logs[] = 'ZIP SHA256 = ' . $hash;

            $results[$productId] = $this->row(
                $productId,
                $title,
                'READY',
                $hash,
                $logs
            );
        }

        return $results;
    }

    /**
     * @param array<int|string, mixed> $queueRows
     * @param list<int|string> $selectedIds
     * @param array<int, array<string, mixed>> $archiveFiles
     * @param array<int|string, mixed> $preparedRows
     * @return array<int, array<string, mixed>>
     */
    public function apply(
        array $queueRows,
        array $selectedIds,
        array $archiveFiles,
        array $preparedRows
    ): array {
        $ids = $this->selectedIds($selectedIds);
        $results = [];

        foreach ($ids as $productId) {
            $queueRow = $this->queueRow($queueRows, $productId);
            $title = $this->title($queueRow, $productId);
            $prepared = $preparedRows[$productId]
                ?? $preparedRows[(string) $productId]
                ?? null;

            if (
                ! is_array($prepared)
                || (string) ($prepared['status'] ?? '') !== 'READY'
            ) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    [
                        'BATCH APPLY = STOP',
                        'PREFLIGHT READY STATE = REQUIRED',
                    ]
                );

                continue;
            }

            $file = $archiveFiles[$productId] ?? [];

            if (! $this->validUpload($file)) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    ['BATCH APPLY = STOP', 'ZIP FILE = REQUIRED AGAIN']
                );

                continue;
            }

            $expectedHash = trim((string) ($prepared['sha256'] ?? ''));
            $actualHash = $this->fileHash($file);

            if (
                $expectedHash === ''
                || $actualHash === ''
                || ! hash_equals($expectedHash, $actualHash)
            ) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    [
                        'BATCH APPLY = STOP',
                        'ZIP SHA256 DOES NOT MATCH PREFLIGHT',
                    ]
                );

                continue;
            }

            try {
                $data = $this->data($productId, $queueRow);
                $result = $this->coordinator->update($data, $file);
                $logs = $result->logs;
            } catch (Throwable $exception) {
                $results[$productId] = $this->row(
                    $productId,
                    $title,
                    'STOP',
                    '',
                    [
                        'BATCH APPLY = STOP',
                        'ERROR MESSAGE = ' . $exception->getMessage(),
                    ]
                );

                continue;
            }

            $logs[] = $result->success
                ? 'BATCH APPLY = READY'
                : 'BATCH APPLY = STOP';

            $results[$productId] = $this->row(
                $productId,
                $title,
                $result->success ? 'UPDATED' : 'STOP',
                $result->success ? '' : $expectedHash,
                $logs
            );
        }

        return $results;
    }

    /**
     * @param list<int|string> $selectedIds
     * @return list<int>
     */
    private function selectedIds(array $selectedIds): array
    {
        $ids = [];

        foreach ($selectedIds as $rawId) {
            $productId = (int) $rawId;

            if ($productId > 0) {
                $ids[$productId] = $productId;
            }
        }

        $ids = array_values($ids);

        if ($ids === []) {
            throw new RuntimeException('Select at least one product for Batch ZIP Update.');
        }

        if (count($ids) > self::MAX_BATCH) {
            throw new RuntimeException(
                'Batch ZIP Update is limited to '
                . self::MAX_BATCH
                . ' products per run.'
            );
        }

        return $ids;
    }

    /**
     * @param array<int|string, mixed> $queueRows
     * @return array<string, mixed>
     */
    private function queueRow(array $queueRows, int $productId): array
    {
        $row = $queueRows[$productId]
            ?? $queueRows[(string) $productId]
            ?? null;

        if (
            ! is_array($row)
            || (string) ($row['status'] ?? '') !== 'UPDATE_AVAILABLE'
        ) {
            throw new RuntimeException(
                'Product '
                . $productId
                . ' is not an UPDATE_AVAILABLE queue item.'
            );
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $queueRow
     */
    private function data(
        int $productId,
        array $queueRow
    ): ProductUpdateData {
        $snapshot = $this->updater->load($productId);
        $sourceUpdateDate = trim(
            (string) ($queueRow['envatoUpdateDate'] ?? '')
        );

        if ($sourceUpdateDate === '') {
            $sourceUpdateDate = $snapshot->sourceUpdateDate;
        }

        return new ProductUpdateData(
            $productId,
            $snapshot->baseTitle,
            $snapshot->itemId,
            $snapshot->version,
            trim((string) ($queueRow['envatoVersion'] ?? '')),
            $sourceUpdateDate,
            $snapshot->salesPage,
            $snapshot->skuFilename,
            $snapshot->skuFilename,
            $snapshot->downloadUrl
        );
    }

    /**
     * @param array<string, mixed> $file
     */
    private function validUpload(array $file): bool
    {
        return $file !== []
            && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && trim((string) ($file['tmp_name'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $file
     */
    private function fileHash(array $file): string
    {
        $path = trim((string) ($file['tmp_name'] ?? ''));

        if ($path === '' || ! is_file($path)) {
            return '';
        }

        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : '';
    }

    /**
     * @param list<string> $logs
     */
    private function zipVersion(array $logs): string
    {
        $prefix = 'ZIP VERSION = SOURCE OF TRUTH: ';

        foreach ($logs as $line) {
            if (str_starts_with($line, $prefix)) {
                return trim(substr($line, strlen($prefix)));
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $queueRow
     */
    private function title(array $queueRow, int $productId): string
    {
        $title = trim((string) ($queueRow['title'] ?? ''));

        return $title !== '' ? $title : 'Product #' . $productId;
    }

    /**
     * @param list<string> $logs
     * @return array<string, mixed>
     */
    private function row(
        int $productId,
        string $title,
        string $status,
        string $sha256,
        array $logs
    ): array {
        return [
            'productId' => $productId,
            'title' => $title,
            'status' => $status,
            'sha256' => $sha256,
            'logs' => $logs,
        ];
    }
}
