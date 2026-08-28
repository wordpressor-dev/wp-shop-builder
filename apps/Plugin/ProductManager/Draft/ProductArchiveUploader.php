<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductArchiveUploader
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    /**
     * @param array<string, mixed> $file
     */
    public function storeForCreate(
        array $file,
        string $baseTitle,
        string $salesPage,
        int $itemId,
        string $version
    ): ProductArchiveUploadResult {
        return $this->store(
            $file,
            $baseTitle,
            $salesPage,
            $itemId,
            $version,
            false
        );
    }

    /**
     * @param array<string, mixed> $file
     */
    public function storeForUpdate(
        array $file,
        string $baseTitle,
        string $salesPage,
        int $itemId,
        string $version
    ): ProductArchiveUploadResult {
        return $this->store(
            $file,
            $baseTitle,
            $salesPage,
            $itemId,
            $version,
            true
        );
    }

    /**
     * @return list<string>
     */
    public function finalize(ProductArchiveUploadResult $result): array
    {
        if (! $result->success || ! $result->supplied) {
            return [];
        }

        if (
            $result->backupPath !== ''
            && $this->exists($result->backupPath)
        ) {
            if (! (bool) ($this->call)('unlink', $result->backupPath)) {
                return [
                    'ARCHIVE BACKUP CLEANUP = FAILED',
                    'ARCHIVE BACKUP PATH = ' . $result->backupPath,
                ];
            }

            return ['ARCHIVE BACKUP CLEANUP = READY'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function rollback(ProductArchiveUploadResult $result): array
    {
        if (! $result->success || ! $result->supplied) {
            return [];
        }

        $logs = ['ARCHIVE ROLLBACK = STARTED'];

        if (
            $result->targetPath !== ''
            && $this->exists($result->targetPath)
        ) {
            if (! (bool) ($this->call)('unlink', $result->targetPath)) {
                $logs[] = 'ARCHIVE ROLLBACK NEW FILE = FAILED';

                return $logs;
            }
        }

        if (
            $result->backupPath !== ''
            && $this->exists($result->backupPath)
        ) {
            if (! (bool) ($this->call)(
                'rename',
                $result->backupPath,
                $result->targetPath
            )) {
                $logs[] = 'ARCHIVE ROLLBACK OLD FILE = FAILED';

                return $logs;
            }

            $logs[] = 'ARCHIVE ROLLBACK OLD FILE = RESTORED';
        }

        $logs[] = 'ARCHIVE ROLLBACK = READY';

        return $logs;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function store(
        array $file,
        string $baseTitle,
        string $salesPage,
        int $itemId,
        string $version,
        bool $replaceExisting
    ): ProductArchiveUploadResult {
        $error = isset($file['error'])
            ? (int) $file['error']
            : UPLOAD_ERR_NO_FILE;

        if ($file === [] || $error === UPLOAD_ERR_NO_FILE) {
            return new ProductArchiveUploadResult(
                true,
                false,
                '',
                '',
                '',
                '',
                ['ARCHIVE UPLOAD = NOT SUPPLIED']
            );
        }

        if ($error !== UPLOAD_ERR_OK) {
            return $this->failure(
                'ARCHIVE UPLOAD ERROR = ' . $error
            );
        }

        $originalName = $this->string($file['name'] ?? null);
        $tmpName = $this->string($file['tmp_name'] ?? null);

        if (
            $originalName === ''
            || strtolower((string) pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )) !== 'zip'
        ) {
            return $this->failure(
                'ARCHIVE VALIDATION = ZIP FILE REQUIRED'
            );
        }

        if ($tmpName === '') {
            return $this->failure(
                'ARCHIVE VALIDATION = TEMPORARY FILE MISSING'
            );
        }

        try {
            if (! (bool) ($this->call)('is_uploaded_file', $tmpName)) {
                return $this->failure(
                    'ARCHIVE VALIDATION = INVALID UPLOAD SOURCE'
                );
            }

            $signature = ($this->call)(
                'file_get_contents',
                $tmpName,
                false,
                null,
                0,
                4
            );

            if (! $this->validZipSignature($signature)) {
                return $this->failure(
                    'ARCHIVE VALIDATION = INVALID ZIP SIGNATURE'
                );
            }

            $productType = CatalogProductType::infer(
                $baseTitle,
                $salesPage
            );
            $skuFilename = ProductSkuFilename::build(
                $itemId,
                $salesPage,
                $version
            );
            $uploads = ($this->call)('wp_upload_dir');

            if (! is_array($uploads)) {
                return $this->failure(
                    'ARCHIVE STORAGE = WORDPRESS UPLOADS UNAVAILABLE'
                );
            }

            $uploadError = $this->string($uploads['error'] ?? null);

            if ($uploadError !== '') {
                return $this->failure(
                    'ARCHIVE STORAGE ERROR = ' . $uploadError
                );
            }

            $baseDir = $this->string($uploads['basedir'] ?? null);
            $baseUrl = $this->string($uploads['baseurl'] ?? null);

            if ($baseDir === '' || $baseUrl === '') {
                return $this->failure(
                    'ARCHIVE STORAGE = BASE PATH OR URL MISSING'
                );
            }

            $storage = CatalogProductType::storageFolder($productType);
            $vendor = ProductDownloadUrl::vendorFolder($skuFilename);
            $storagePath = $storage
                . ($vendor !== '' ? '/' . $vendor : '');
            $directory = rtrim($baseDir, '/\\')
                . '/woocommerce_uploads/'
                . $storagePath
                . '/'
                . $itemId;

            if (! (bool) ($this->call)('wp_mkdir_p', $directory)) {
                return $this->failure(
                    'ARCHIVE STORAGE = DIRECTORY CREATE FAILED'
                );
            }

            $targetPath = $directory . '/' . $skuFilename;
            $backupPath = '';

            if ($this->exists($targetPath)) {
                if (! $replaceExisting) {
                    return $this->failure(
                        'ARCHIVE TARGET ALREADY EXISTS = '
                        . $skuFilename
                    );
                }

                $backupPath = $targetPath . '.wp-shop-backup';

                if ($this->exists($backupPath)) {
                    return $this->failure(
                        'ARCHIVE BACKUP ALREADY EXISTS; MANUAL REVIEW REQUIRED'
                    );
                }

                if (! (bool) ($this->call)(
                    'rename',
                    $targetPath,
                    $backupPath
                )) {
                    return $this->failure(
                        'ARCHIVE BACKUP = FAILED'
                    );
                }
            }

            if (! (bool) ($this->call)(
                'move_uploaded_file',
                $tmpName,
                $targetPath
            )) {
                if (
                    $backupPath !== ''
                    && $this->exists($backupPath)
                ) {
                    ($this->call)(
                        'rename',
                        $backupPath,
                        $targetPath
                    );
                }

                return $this->failure(
                    'ARCHIVE MOVE = FAILED'
                );
            }

            $downloadUrl = ProductDownloadUrl::build(
                $baseUrl,
                $productType,
                $itemId,
                $skuFilename
            );

            if ($downloadUrl === '') {
                $result = new ProductArchiveUploadResult(
                    true,
                    true,
                    $skuFilename,
                    '',
                    $targetPath,
                    $backupPath,
                    []
                );
                $rollbackLogs = $this->rollback($result);

                return new ProductArchiveUploadResult(
                    false,
                    true,
                    '',
                    '',
                    '',
                    '',
                    array_merge(
                        ['ARCHIVE DOWNLOAD URL = BUILD FAILED'],
                        $rollbackLogs
                    )
                );
            }

            return new ProductArchiveUploadResult(
                true,
                true,
                $skuFilename,
                $downloadUrl,
                $targetPath,
                $backupPath,
                [
                    'ARCHIVE UPLOAD = READY',
                    'ARCHIVE ORIGINAL NAME = ' . $originalName,
                    'ARCHIVE CANONICAL NAME = ' . $skuFilename,
                    'ARCHIVE STORAGE = ' . $storagePath,
                    'ARCHIVE ITEM DIRECTORY = ' . $itemId,
                    'DOWNLOAD URL = ' . $downloadUrl,
                    $backupPath !== ''
                        ? 'ARCHIVE EXISTING FILE = BACKED UP'
                        : 'ARCHIVE EXISTING FILE = NOT PRESENT',
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure(
                'ARCHIVE UPLOAD EXCEPTION = ' . $exception->getMessage()
            );
        }
    }

    private function exists(string $path): bool
    {
        return (bool) ($this->call)('file_exists', $path);
    }

    private function validZipSignature(mixed $signature): bool
    {
        if (! is_string($signature) || strlen($signature) < 4) {
            return false;
        }

        return in_array(
            $signature,
            [
                "PK\x03\x04",
                "PK\x05\x06",
                "PK\x07\x08",
            ],
            true
        );
    }

    private function string(mixed $value): string
    {
        return is_scalar($value)
            ? trim((string) $value)
            : '';
    }

    private function failure(string $message): ProductArchiveUploadResult
    {
        return new ProductArchiveUploadResult(
            false,
            true,
            '',
            '',
            '',
            '',
            [
                'ARCHIVE UPLOAD = FAILED',
                $message,
                'PRODUCT WRITE = BLOCKED',
            ]
        );
    }
}
