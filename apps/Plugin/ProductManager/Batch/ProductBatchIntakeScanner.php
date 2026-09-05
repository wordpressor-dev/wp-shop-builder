<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Closure;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\ProductSourceType;
use WPShop\App\Plugin\ProductManager\Draft\ProductDownloadUrl;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;
use WPShop\App\Plugin\ProductManager\Draft\ProductVendorSkuFilename;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemSearchResolver;
use WPShop\App\Plugin\ProductManager\Envato\WordPressEnvatoTransport;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveVersionInspector;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateData;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateResult;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;

final class ProductBatchIntakeScanner
{
    public const MAX_AUTO_UPDATE_BATCH = 10;

    /** @var list<array{id:int,itemId:int,type:string,slug:string,haystack:string}>|null */
    private ?array $identityCatalog = null;

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly ProductArchiveVersionInspector $versionInspector,
        private readonly ProductArchiveIdentityInspector $identityInspector,
        private readonly ?EnvatoItemSearchResolver $envatoSearchResolver = null
    ) {
    }

    public function inboxDirectory(string $uploadsBaseDir): string
    {
        $uploadsBaseDir = rtrim(trim($uploadsBaseDir), '/\\');

        if ($uploadsBaseDir === '') {
            throw new RuntimeException('Uploads base directory is unavailable.');
        }

        return $uploadsBaseDir
            . DIRECTORY_SEPARATOR . 'woocommerce_uploads'
            . DIRECTORY_SEPARATOR . 'INBOX';
    }

    public function ensureInbox(string $uploadsBaseDir): string
    {
        $directory = $this->inboxDirectory($uploadsBaseDir);

        if (is_dir($directory)) {
            return $directory;
        }

        $created = (bool) ($this->call)('wp_mkdir_p', $directory);

        if (! $created && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create Product Manager INBOX directory.');
        }

        return $directory;
    }

    /** @return list<string> */
    public function folders(string $uploadsBaseDir): array
    {
        $root = $this->ensureInbox($uploadsBaseDir);
        $entries = scandir($root);

        if ($entries === false) {
            return [];
        }

        $folders = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '_')) {
                continue;
            }

            if (is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                $folders[] = $entry;
            }
        }

        natcasesort($folders);

        return array_values($folders);
    }

    /**
     * @return list<array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }>
     */
    public function scan(string $uploadsBaseDir, string $folder): array
    {
        $root = $this->ensureInbox($uploadsBaseDir);
        $folder = $this->normalizeFolder($folder);
        $directory = $folder === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . $folder;

        if (! is_dir($directory)) {
            throw new RuntimeException('Selected INBOX folder does not exist.');
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException('Cannot read selected INBOX folder.');
        }

        $rows = [];

        foreach ($entries as $entry) {
            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (
                $entry === '.'
                || $entry === '..'
                || ! is_file($path)
                || strtolower((string) pathinfo($entry, PATHINFO_EXTENSION)) !== 'zip'
            ) {
                continue;
            }

            $rows[] = $this->inspectArchive($path, $entry, $folder);
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => strnatcasecmp(
                $left['filename'],
                $right['filename']
            )
        );

        return $rows;
    }

    /**
     * @return array{
     *   processed: int,
     *   updated: int,
     *   failed: int,
     *   remaining: int,
     *   continue: bool,
     *   logs: list<string>
     * }
     */
    public function applyReadyUpdates(
        string $uploadsBaseDir,
        string $folder,
        int $limit = self::MAX_AUTO_UPDATE_BATCH
    ): array {
        $limit = max(1, min(self::MAX_AUTO_UPDATE_BATCH, $limit));
        $readyRows = $this->readyUpdateRows(
            $this->scan($uploadsBaseDir, $folder)
        );
        $batch = array_slice($readyRows, 0, $limit);
        $processed = 0;
        $updated = 0;
        $failed = 0;
        $canContinue = true;
        $logs = [
            'AUTO BATCH UPDATE = RECEIVED',
            'BATCH LIMIT = ' . $limit,
            'READY BEFORE = ' . count($readyRows),
        ];

        foreach ($batch as $row) {
            ++$processed;
            $filename = $row['filename'];
            $productId = $row['productId'];
            $logs[] = str_repeat('=', 72);
            $logs[] = 'AUTO ITEM = ' . $filename;
            $logs[] = 'AUTO PRODUCT ID = ' . $productId;
            $result = $this->applyUpdate(
                $uploadsBaseDir,
                $folder,
                $filename
            );

            foreach ($result->logs as $line) {
                $logs[] = $line;
            }

            if ($result->success) {
                ++$updated;
                $logs[] = 'AUTO ITEM RESULT = UPDATED';

                continue;
            }

            ++$failed;
            $logs[] = 'AUTO ITEM RESULT = REVIEW';

            try {
                $target = $this->moveToBucket(
                    $uploadsBaseDir,
                    $folder,
                    $filename,
                    '_REVIEW'
                );
                $logs[] = 'FAILED ZIP MOVED TO = ' . $target;
            } catch (Throwable $exception) {
                $canContinue = false;
                $logs[] = 'FAILED ZIP MOVE TO REVIEW = FAILED';
                $logs[] = 'FAILED ZIP MOVE ERROR = ' . $exception->getMessage();
                $logs[] = 'AUTO CONTINUE = BLOCKED FOR MANUAL REVIEW';
            }
        }

        $remaining = count(
            $this->readyUpdateRows(
                $this->scan($uploadsBaseDir, $folder)
            )
        );
        $logs[] = str_repeat('=', 72);
        $logs[] = 'AUTO BATCH PROCESSED = ' . $processed;
        $logs[] = 'AUTO BATCH UPDATED = ' . $updated;
        $logs[] = 'AUTO BATCH REVIEW = ' . $failed;
        $logs[] = 'AUTO BATCH REMAINING READY = ' . $remaining;
        $logs[] = $remaining > 0
            ? 'AUTO BATCH CONTINUE = REQUIRED'
            : 'AUTO BATCH UPDATE = COMPLETE';

        return [
            'processed' => $processed,
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => $remaining,
            'continue' => $canContinue && $remaining > 0,
            'logs' => $logs,
        ];
    }

    /**
     * @param list<array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }> $rows
     * @return list<array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }>
     */
    public function readyUpdateRows(array $rows): array
    {
        return array_values(
            array_filter(
                $rows,
                static fn (array $row): bool => $row['action'] === 'UPDATE'
                    && $row['status'] === 'READY'
            )
        );
    }

    public function applyUpdate(
        string $uploadsBaseDir,
        string $folder,
        string $filename
    ): ProductUpdateResult {
        $folder = $this->normalizeFolder($folder);
        $filename = $this->normalizeFilename($filename);
        $sourcePath = $this->archivePath($uploadsBaseDir, $folder, $filename);

        if (! is_file($sourcePath)) {
            return $this->batchFailure('BATCH APPLY = SOURCE ZIP NOT FOUND');
        }

        $row = $this->rowByFilename($uploadsBaseDir, $folder, $filename);

        if ($row === null) {
            return $this->batchFailure('BATCH APPLY = ZIP NOT FOUND IN SCAN');
        }

        if ($row['action'] !== 'UPDATE' || $row['status'] !== 'READY') {
            return $this->batchFailure(
                'BATCH APPLY = ONLY READY UPDATE ROWS CAN BE APPLIED'
            );
        }

        $updater = new ProductVersionUpdater($this->call);

        try {
            $snapshot = $updater->load($row['productId']);
        } catch (Throwable $exception) {
            return $this->batchFailure(
                'BATCH APPLY LOAD ERROR = ' . $exception->getMessage()
            );
        }

        $sourceType = $this->productSourceType(
            $snapshot->productId,
            $snapshot->salesPage
        );
        $sourceUpdateDate = '';

        if ($sourceType === ProductSourceType::ENVATO) {
            $token = $this->token();

            if ($token === '') {
                return $this->batchFailure(
                    'BATCH APPLY = ENVATO TOKEN REQUIRED FOR OFFICIAL UPDATE DATE'
                );
            }

            try {
                $transport = new WordPressEnvatoTransport();
                $client = new EnvatoClient(
                    $transport(...),
                    new EnvatoItemMapper()
                );
                $suggestion = (new ProductUpdateEnvatoAdvisor($client))->suggest(
                    $snapshot,
                    $token
                );
            } catch (Throwable $exception) {
                return $this->batchFailure(
                    'BATCH APPLY ENVATO ERROR = ' . $exception->getMessage()
                );
            }

            if ($suggestion->updateDate === '') {
                return $this->batchFailure(
                    'BATCH APPLY = ENVATO UPDATE DATE NOT PROVIDED'
                );
            }

            $sourceUpdateDate = $suggestion->updateDate;
        } else {
            $sourceUpdateDate = (string) ($this->call)(
                'current_time',
                'Y-m-d'
            );

            if (
                preg_match(
                    '/^\\d{4}-\\d{2}-\\d{2}$/',
                    $sourceUpdateDate
                ) !== 1
            ) {
                return $this->batchFailure(
                    'BATCH APPLY = VENDOR IMPORT DATE NOT AVAILABLE'
                );
            }
        }

        $productType = $row['productType'];
        $newVersion = $productType === CatalogProductType::TEMPLATE_KIT
            ? ''
            : $row['detectedVersion'];

        try {
            $skuFilename = $sourceType === ProductSourceType::VENDOR
                ? ProductVendorSkuFilename::synchronize(
                    $snapshot->skuFilename,
                    $snapshot->version,
                    $newVersion
                )
                : ProductSkuFilename::build(
                    $snapshot->itemId,
                    $snapshot->salesPage,
                    $newVersion
                );
        } catch (\InvalidArgumentException $exception) {
            return $this->batchFailure(
                'BATCH APPLY SKU ERROR = ' . $exception->getMessage()
            );
        }

        $uploads = ($this->call)('wp_upload_dir');

        if (! is_array($uploads)) {
            return $this->batchFailure(
                'BATCH APPLY = WORDPRESS UPLOADS UNAVAILABLE'
            );
        }

        $baseDir = trim((string) ($uploads['basedir'] ?? ''));
        $baseUrl = trim((string) ($uploads['baseurl'] ?? ''));

        if ($baseDir === '' || $baseUrl === '') {
            return $this->batchFailure(
                'BATCH APPLY = UPLOAD BASE PATH OR URL MISSING'
            );
        }

        if (! $this->validZipSignature($sourcePath)) {
            return $this->batchFailure('BATCH APPLY = INVALID ZIP SIGNATURE');
        }

        if ($sourceType === ProductSourceType::VENDOR) {
            $vendorTarget = $this->vendorArchiveTarget(
                $baseDir,
                $snapshot->downloadUrl,
                $skuFilename
            );

            if ($vendorTarget === null) {
                return $this->batchFailure(
                    'BATCH APPLY = VENDOR DOWNLOAD PATH CANNOT BE PRESERVED'
                );
            }

            $storagePath = $vendorTarget['storagePath'];
            $directory = $vendorTarget['directory'];
            $downloadUrl = $vendorTarget['downloadUrl'];
            $itemDirectory = '[preserved vendor path]';
        } else {
            $storage = CatalogProductType::storageFolder($productType);
            $vendor = ProductDownloadUrl::vendorFolder($skuFilename);
            $storagePath = $storage
                . ($vendor !== '' ? '/' . $vendor : '');
            $directory = rtrim($baseDir, '/\\')
                . '/woocommerce_uploads/'
                . $storagePath
                . '/'
                . $snapshot->itemId;
            $downloadUrl = ProductDownloadUrl::build(
                $baseUrl,
                $productType,
                $snapshot->itemId,
                $skuFilename
            );
            $itemDirectory = (string) $snapshot->itemId;
        }

        if ($downloadUrl === '') {
            return $this->batchFailure(
                'BATCH APPLY = DOWNLOAD URL BUILD FAILED'
            );
        }

        if (! (bool) ($this->call)('wp_mkdir_p', $directory)) {
            return $this->batchFailure(
                'BATCH APPLY = TARGET DIRECTORY CREATE FAILED'
            );
        }

        $targetPath = $directory . '/' . $skuFilename;
        $backupPath = '';

        if ((bool) ($this->call)('file_exists', $targetPath)) {
            $backupPath = $targetPath . '.wp-shop-batch-backup';

            if ((bool) ($this->call)('file_exists', $backupPath)) {
                return $this->batchFailure(
                    'BATCH APPLY = BACKUP ALREADY EXISTS; REVIEW REQUIRED'
                );
            }

            if (! (bool) ($this->call)('rename', $targetPath, $backupPath)) {
                return $this->batchFailure('BATCH APPLY = BACKUP FAILED');
            }
        }

        if (! (bool) ($this->call)('copy', $sourcePath, $targetPath)) {
            $this->restoreBackup($targetPath, $backupPath);

            return $this->batchFailure('BATCH APPLY = ZIP COPY FAILED');
        }

        $data = new ProductUpdateData(
            $snapshot->productId,
            $snapshot->baseTitle,
            $snapshot->itemId,
            $snapshot->version,
            $newVersion,
            $sourceUpdateDate,
            $snapshot->salesPage,
            $snapshot->skuFilename,
            $skuFilename,
            $downloadUrl,
            $sourceType
        );
        $preflight = $updater->preflight($data);

        if (! $preflight->success) {
            $this->rollbackTarget($targetPath, $backupPath);

            return new ProductUpdateResult(
                false,
                array_merge(
                    [
                        'BATCH APPLY = RECEIVED',
                        'BATCH ZIP = ' . $filename,
                        'BATCH PRODUCT ID = ' . $snapshot->productId,
                    ],
                    $preflight->logs,
                    ['BATCH APPLY = PREFLIGHT FAILED', 'BATCH ROLLBACK = READY']
                )
            );
        }

        $result = $updater->update($data);

        if (! $result->success) {
            $this->rollbackTarget($targetPath, $backupPath);

            return new ProductUpdateResult(
                false,
                array_merge(
                    ['BATCH APPLY = RECEIVED', 'BATCH ZIP = ' . $filename],
                    $preflight->logs,
                    $result->logs,
                    ['BATCH APPLY = PRODUCT UPDATE FAILED', 'BATCH ROLLBACK = READY']
                )
            );
        }

        $cleanupLogs = [];

        if (
            $backupPath !== ''
            && (bool) ($this->call)('file_exists', $backupPath)
        ) {
            $cleanupLogs[] = (bool) ($this->call)('unlink', $backupPath)
                ? 'BATCH BACKUP CLEANUP = READY'
                : 'BATCH BACKUP CLEANUP = FAILED';
        }

        $cleanupLogs[] = (bool) ($this->call)('unlink', $sourcePath)
            ? 'BATCH INBOX SOURCE = REMOVED'
            : 'BATCH INBOX SOURCE = CLEANUP FAILED';

        return new ProductUpdateResult(
            true,
            array_merge(
                [
                    'BATCH APPLY = RECEIVED',
                    'BATCH ZIP = ' . $filename,
                    'BATCH PRODUCT ID = ' . $snapshot->productId,
                    'BATCH ACTION = UPDATE',
                    'ZIP VERSION = SOURCE OF TRUTH: '
                        . ($newVersion !== '' ? $newVersion : '[versionless]'),
                    'SOURCE TYPE = ' . strtoupper($sourceType),
                    (
                        $sourceType === ProductSourceType::ENVATO
                            ? 'ENVATO UPDATE DATE = '
                            : 'VENDOR UPDATE DATE = IMPORT DATE: '
                    ) . $sourceUpdateDate,
                    'ARCHIVE CANONICAL NAME = ' . $skuFilename,
                    'ARCHIVE STORAGE = ' . $storagePath,
                    'ARCHIVE ITEM DIRECTORY = ' . $itemDirectory,
                    'DOWNLOAD URL = ' . $downloadUrl,
                ],
                $preflight->logs,
                $result->logs,
                $cleanupLogs,
                ['BATCH UPDATE = READY']
            )
        );
    }

    public function moveToBucket(
        string $uploadsBaseDir,
        string $folder,
        string $filename,
        string $bucket
    ): string {
        $folder = $this->normalizeFolder($folder);
        $filename = $this->normalizeFilename($filename);

        if (! in_array($bucket, ['_SKIPPED', '_REVIEW'], true)) {
            throw new RuntimeException('Unsupported INBOX bucket.');
        }

        $source = $this->archivePath($uploadsBaseDir, $folder, $filename);

        if (! is_file($source)) {
            throw new RuntimeException('Source ZIP does not exist.');
        }

        $targetDir = $this->ensureInbox($uploadsBaseDir)
            . DIRECTORY_SEPARATOR . $bucket
            . ($folder !== '' ? DIRECTORY_SEPARATOR . $folder : '');

        if (! (bool) ($this->call)('wp_mkdir_p', $targetDir)) {
            throw new RuntimeException('Cannot create INBOX bucket directory.');
        }

        $target = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if ((bool) ($this->call)('file_exists', $target)) {
            throw new RuntimeException(
                'Target bucket already contains this ZIP; manual review required.'
            );
        }

        if (! (bool) ($this->call)('rename', $source, $target)) {
            throw new RuntimeException('Cannot move ZIP to INBOX bucket.');
        }

        return $bucket . '/' . ($folder !== '' ? $folder . '/' : '') . $filename;
    }

    /**
     * @return array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }|null
     */
    private function rowByFilename(
        string $uploadsBaseDir,
        string $folder,
        string $filename
    ): ?array {
        foreach ($this->scan($uploadsBaseDir, $folder) as $row) {
            if ($row['filename'] === $filename) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }
     */
    private function inspectArchive(
        string $path,
        string $filename,
        string $folder
    ): array {
        $identity = $this->identityInspector->inspect($path, $filename);
        $itemId = $this->itemIdFromFilename($filename);
        $productId = $itemId > 0 ? $this->productIdByItemId($itemId) : 0;
        $autoEnvatoNote = '';

        if ($productId <= 0 && $identity->success && $identity->name !== '') {
            $productId = $this->productIdByArchiveIdentity(
                $identity->name,
                $identity->productType
            );

            if ($productId > 0) {
                $itemId = $this->productItemId($productId);
            }
        }

        if (
            $productId <= 0
            && $itemId <= 0
            && $identity->success
            && $identity->name !== ''
            && $identity->productType !== ''
            && $this->envatoSearchResolver !== null
        ) {
            $resolved = $this->envatoSearchResolver->resolve(
                $identity->name,
                $identity->productType,
                $this->token()
            );

            if ($resolved->success && $resolved->itemId > 0) {
                $itemId = $resolved->itemId;
                $productId = $this->productIdByItemId($itemId);
                $autoEnvatoNote = 'ENVATO AUTO-MATCH: '
                    . $resolved->title
                    . ' ['
                    . $resolved->score
                    . '%]';
            } elseif ($resolved->message !== '') {
                $autoEnvatoNote = $resolved->message;
            }
        }

        $productTitle = $productId > 0
            ? trim((string) ($this->call)('get_the_title', $productId))
            : '';
        $productType = $productId > 0
            ? $this->existingProductType($productId, $productTitle)
            : ($identity->success ? $identity->productType : '');

        if (
            $productId > 0
            && $productType === ''
            && $identity->success
            && $identity->productType !== ''
        ) {
            $productType = $identity->productType;
        }
        $currentVersion = $productId > 0
            ? $this->currentVersion($productId)
            : '';
        $detectedVersion = '';
        $status = 'READY';
        $note = '';

        if ($productType === '') {
            $status = 'REVIEW';
            $note = 'PRODUCT TYPE NOT DETECTED';
        } elseif ($productType === CatalogProductType::TEMPLATE_KIT) {
            $detectedVersion = '—';
            $note = 'VERSIONLESS TEMPLATE KIT';
        } elseif (
            $identity->success
            && $identity->productType === $productType
            && $identity->version !== ''
        ) {
            $detectedVersion = $identity->version;
            $note = 'MATCHED BY ZIP IDENTITY: '
                . $identity->name . ' (' . $identity->source . ')';
        } else {
            $inspection = $this->versionInspector->inspect(
                [
                    'name' => $filename,
                    'tmp_name' => $path,
                    'error' => UPLOAD_ERR_OK,
                ],
                $productType
            );

            if (! $inspection->success) {
                $status = 'REVIEW';
                $note = $inspection->logs[0] ?? 'ZIP VERSION NOT DETECTED';
            } else {
                $detectedVersion = $inspection->version;
                $note = $inspection->logs[2] ?? 'ZIP INSPECTION = READY';
            }
        }

        if ($itemId <= 0 && $productId <= 0) {
            $status = 'REVIEW';
            $note = $note !== ''
                ? $note . '; ITEM ID NOT DETECTED; PRODUCT MATCH REQUIRED'
                : 'ITEM ID NOT DETECTED; PRODUCT MATCH REQUIRED';
        }

        if ($autoEnvatoNote !== '') {
            $note = $note !== ''
                ? $note . '; ' . $autoEnvatoNote
                : $autoEnvatoNote;
        }

        if (
            $productId > 0
            && $status === 'READY'
            && $productType !== CatalogProductType::TEMPLATE_KIT
            && $currentVersion !== ''
            && $detectedVersion !== ''
            && $detectedVersion !== '—'
            && version_compare(
                $detectedVersion,
                $currentVersion,
                '<='
            )
        ) {
            $status = 'STOP';
            $note = version_compare(
                $detectedVersion,
                $currentVersion,
                '=='
            )
                ? 'ZIP VERSION = CURRENT; UPDATE NOT REQUIRED'
                : 'ZIP VERSION OLDER THAN CURRENT; DOWNGRADE BLOCKED';
        }

        return [
            'filename' => $filename,
            'relativePath' => ($folder !== '' ? $folder . '/' : '') . $filename,
            'itemId' => $itemId,
            'productId' => $productId,
            'productTitle' => $productTitle,
            'productType' => $productType,
            'currentVersion' => $currentVersion,
            'detectedVersion' => $detectedVersion,
            'action' => $productId > 0
                ? 'UPDATE'
                : ($itemId > 0 ? 'CREATE' : 'REVIEW'),
            'status' => $status,
            'note' => $note,
        ];
    }

    private function archivePath(
        string $uploadsBaseDir,
        string $folder,
        string $filename
    ): string {
        return $this->ensureInbox($uploadsBaseDir)
            . ($folder !== '' ? DIRECTORY_SEPARATOR . $folder : '')
            . DIRECTORY_SEPARATOR . $filename;
    }

    private function normalizeFolder(string $folder): string
    {
        $folder = trim($folder);

        if ($folder === '') {
            return '';
        }

        if (
            $folder === '.'
            || $folder === '..'
            || basename($folder) !== $folder
            || preg_match('/^[A-Za-z0-9._ -]+$/', $folder) !== 1
        ) {
            throw new RuntimeException('Unsafe INBOX folder name.');
        }

        return $folder;
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if (
            $filename === ''
            || basename($filename) !== $filename
            || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            throw new RuntimeException('Unsafe ZIP filename.');
        }

        return $filename;
    }

    private function itemIdFromFilename(string $filename): int
    {
        if (
            preg_match('/(?:themeforest|codecanyon)-(\d+)-/i', $filename, $matches)
            === 1
        ) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function productItemId(int $productId): int
    {
        $stored = (int) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_item_id',
            true
        );

        if ($stored > 0) {
            return $stored;
        }

        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $fromSalesPage = $this->itemIdFromSalesPage($salesPage);

        if ($fromSalesPage > 0) {
            return $fromSalesPage;
        }

        $sku = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_sku',
            true
        ));

        if (
            preg_match(
                '/^(?:themeforest|codecanyon)-(\d+)-/i',
                $sku,
                $matches
            ) === 1
        ) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function itemIdFromSalesPage(string $salesPage): int
    {
        $path = parse_url(trim($salesPage), PHP_URL_PATH);

        if (! is_string($path)) {
            return 0;
        }

        if (
            preg_match(
                '~/item/[^/]+/(\d+)/?$~',
                $path,
                $matches
            ) !== 1
        ) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function productIdByItemId(int $itemId): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_key' => '_wp_shop_source_item_id',
                'meta_value' => (string) $itemId,
            ]
        );

        if (is_array($ids) && $ids !== []) {
            return (int) reset($ids);
        }

        $matches = [];

        foreach ($this->identityCatalog() as $candidate) {
            if ($candidate['itemId'] === $itemId) {
                $matches[] = $candidate['id'];
            }
        }

        return count($matches) === 1 ? $matches[0] : 0;
    }

    private function productIdByArchiveIdentity(string $name, string $type): int
    {
        $identitySlug = $this->identitySlug($name);

        if ($identitySlug !== '') {
            $exactMatches = [];

            foreach ($this->identityCatalog() as $candidate) {
                if (
                    $type !== ''
                    && $candidate['type'] !== ''
                    && $candidate['type'] !== $type
                ) {
                    continue;
                }

                if ($candidate['slug'] === $identitySlug) {
                    $exactMatches[] = $candidate['id'];
                }
            }

            if (count($exactMatches) === 1) {
                return $exactMatches[0];
            }

            if (count($exactMatches) > 1) {
                return 0;
            }
        }

        $tokens = $this->identityTokens($name);

        if ($tokens === []) {
            return 0;
        }

        $matches = [];

        foreach ($this->identityCatalog() as $candidate) {
            if (
                $type !== ''
                && $candidate['type'] !== ''
                && $candidate['type'] !== $type
            ) {
                continue;
            }

            $allFound = true;

            foreach ($tokens as $token) {
                if (! str_contains($candidate['haystack'], $token)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                $matches[] = $candidate['id'];
            }
        }

        return count($matches) === 1 ? $matches[0] : 0;
    }

    /**
     * @return list<array{id:int,itemId:int,type:string,slug:string,haystack:string}>
     */
    private function identityCatalog(): array
    {
        if ($this->identityCatalog !== null) {
            return $this->identityCatalog;
        }

        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]
        );

        if (! is_array($ids)) {
            $this->identityCatalog = [];

            return $this->identityCatalog;
        }

        $catalog = [];

        foreach ($ids as $candidateId) {
            $candidateId = (int) $candidateId;

            if ($candidateId <= 0) {
                continue;
            }

            $title = trim((string) ($this->call)(
                'get_the_title',
                $candidateId
            ));
            $slug = trim((string) ($this->call)(
                'get_post_field',
                'post_name',
                $candidateId
            ));
            $sku = trim((string) ($this->call)(
                'get_post_meta',
                $candidateId,
                '_sku',
                true
            ));
            $salesPage = trim((string) ($this->call)(
                'get_post_meta',
                $candidateId,
                'sales_page',
                true
            ));
            $sourceItemId = (int) ($this->call)(
                'get_post_meta',
                $candidateId,
                '_wp_shop_source_item_id',
                true
            );
            $catalogItemId = $sourceItemId > 0
                ? $sourceItemId
                : $this->itemIdFromSalesPage($salesPage);

            if (
                $catalogItemId <= 0
                && preg_match(
                    '/^(?:themeforest|codecanyon)-(\d+)-/i',
                    $sku,
                    $itemMatches
                ) === 1
            ) {
                $catalogItemId = (int) $itemMatches[1];
            }

            $catalog[] = [
                'id' => $candidateId,
                'itemId' => $catalogItemId,
                'type' => $this->existingProductType(
                    $candidateId,
                    $title
                ),
                'slug' => strtolower($slug),
                'haystack' => strtolower(
                    $title . ' ' . $sku . ' ' . $salesPage
                ),
            ];
        }

        $this->identityCatalog = $catalog;

        return $this->identityCatalog;
    }

    private function identitySlug(string $name): string
    {
        $normalized = strtolower(
            (string) preg_replace(
                '/[^a-z0-9]+/i',
                '-',
                trim($name)
            )
        );

        return trim($normalized, '-');
    }

    /** @return list<string> */
    private function identityTokens(string $name): array
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', $name));
        $tokens = preg_split('/\s+/', trim($normalized)) ?: [];
        $stop = [
            'wordpress', 'theme', 'plugin', 'elementor', 'template', 'kit',
            'pro', 'the', 'and', 'for', 'with', 'education',
        ];
        $result = [];

        foreach ($tokens as $token) {
            if (strlen($token) >= 4 && ! in_array($token, $stop, true)) {
                $result[] = $token;
            }
        }

        if ($result === []) {
            $fallbackStop = [
                'wordpress',
                'theme',
                'plugin',
                'template',
                'kit',
                'pro',
                'the',
                'and',
                'for',
                'with',
            ];

            foreach ($tokens as $token) {
                if (
                    strlen($token) >= 4
                    && ! in_array($token, $fallbackStop, true)
                ) {
                    $result[] = $token;
                    break;
                }
            }
        }

        return array_values(array_unique(array_slice($result, 0, 4)));
    }

    private function productSourceType(
        int $productId,
        string $salesPage
    ): string {
        $stored = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_type',
            true
        ));

        return ProductSourceType::normalize(
            $stored,
            $salesPage
        );
    }

    /**
     * @return array{
     *   storagePath:string,
     *   directory:string,
     *   downloadUrl:string
     * }|null
     */
    private function vendorArchiveTarget(
        string $uploadsBaseDir,
        string $currentDownloadUrl,
        string $skuFilename
    ): ?array {
        $uploadsBaseDir = rtrim(trim($uploadsBaseDir), '/\\');
        $currentDownloadUrl = trim($currentDownloadUrl);
        $skuFilename = trim($skuFilename);

        if (
            $uploadsBaseDir === ''
            || $currentDownloadUrl === ''
            || $skuFilename === ''
            || basename($skuFilename) !== $skuFilename
        ) {
            return null;
        }

        $path = parse_url(
            $currentDownloadUrl,
            PHP_URL_PATH
        );

        if (! is_string($path)) {
            return null;
        }

        $marker = '/woocommerce_uploads/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return null;
        }

        $relative = ltrim(
            substr(
                $path,
                $position + strlen($marker)
            ),
            '/'
        );

        if (
            $relative === ''
            || str_contains($relative, '../')
            || str_contains($relative, '..\\')
        ) {
            return null;
        }

        $storagePath = str_replace(
            '\\',
            '/',
            dirname($relative)
        );

        if ($storagePath === '.') {
            return null;
        }

        $urlPrefix = substr(
            $currentDownloadUrl,
            0,
            strrpos($currentDownloadUrl, '/') + 1
        );

        if ($urlPrefix === '') {
            return null;
        }

        return [
            'storagePath' => $storagePath,
            'directory' => $uploadsBaseDir
                . '/woocommerce_uploads/'
                . $storagePath,
            'downloadUrl' => $urlPrefix . $skuFilename,
        ];
    }

    private function existingProductType(int $productId, string $productTitle): string
    {
        $stored = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_product_type',
            true
        ));

        if (
            in_array(
                $stored,
                [
                    CatalogProductType::THEME,
                    CatalogProductType::PLUGIN,
                    CatalogProductType::TEMPLATE_KIT,
                ],
                true
            )
        ) {
            return $stored;
        }

        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));

        return CatalogProductType::infer($productTitle, $salesPage);
    }

    private function currentVersion(int $productId): string
    {
        $version = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'attr_version_value',
            true
        ));

        return $version === '—' ? '' : $version;
    }

    private function token(): string
    {
        if (defined('WP_SHOP_ENVATO_TOKEN')) {
            $configured = constant('WP_SHOP_ENVATO_TOKEN');

            if (is_string($configured) && trim($configured) !== '') {
                return trim($configured);
            }
        }

        return trim((string) ($this->call)(
            'get_option',
            'wp_shop_envato_personal_token',
            ''
        ));
    }

    private function validZipSignature(string $path): bool
    {
        $signature = ($this->call)('file_get_contents', $path, false, null, 0, 4);

        return is_string($signature)
            && in_array(
                $signature,
                ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
                true
            );
    }

    private function restoreBackup(string $targetPath, string $backupPath): void
    {
        if (
            $backupPath !== ''
            && (bool) ($this->call)('file_exists', $backupPath)
        ) {
            ($this->call)('rename', $backupPath, $targetPath);
        }
    }

    private function rollbackTarget(string $targetPath, string $backupPath): void
    {
        if ((bool) ($this->call)('file_exists', $targetPath)) {
            ($this->call)('unlink', $targetPath);
        }

        $this->restoreBackup($targetPath, $backupPath);
    }

    private function batchFailure(string $message): ProductUpdateResult
    {
        return new ProductUpdateResult(
            false,
            ['BATCH APPLY = STOPPED', $message, 'NO PRODUCT WRITTEN = YES']
        );
    }
}
