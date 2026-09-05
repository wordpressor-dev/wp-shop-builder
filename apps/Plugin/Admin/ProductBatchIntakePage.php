<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchCreateAllService;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchCreateCoordinator;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductBatchIntakePage implements SubmenuPageInterface
{
    private const AUTO_UPDATE_STATE_META_KEY = 'wp_shop_pm_import_auto_update_v1';
    private const AUTO_CREATE_STATE_META_KEY = 'wp_shop_pm_import_auto_create_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductBatchIntakeScanner $scanner,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-batch-intake';
    }

    public function title(): string
    {
        return 'Import Queue';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $uploads = ($this->call)('wp_upload_dir');
        $uploadsBaseDir = is_array($uploads)
            ? trim((string) ($uploads['basedir'] ?? ''))
            : '';
        $selectedFolder = trim($this->posted('intake_folder'));
        $filename = trim($this->posted('intake_filename'));
        $itemReference = trim($this->posted('intake_item_reference'));
        $action = $this->posted('wp_shop_pm_batch_intake_action');
        $rows = [];
        $error = '';
        $root = '';
        $folders = [];
        $logs = [];
        $success = null;
        $showResults = false;
        $autoContinue = false;
        $autoCreateContinue = false;
        $autoState = [];
        $autoCreateState = [];

        try {
            $root = $this->scanner->ensureInbox($uploadsBaseDir);
            $folders = $this->scanner->folders($uploadsBaseDir);

            if (
                in_array(
                    $action,
                    ['scan', 'apply', 'apply_all_ready', 'create', 'create_all_new', 'skip', 'review'],
                    true
                )
            ) {
                $this->checkNonce();
                $showResults = true;
            }

            if ($action === 'apply') {
                $result = $this->scanner->applyUpdate(
                    $uploadsBaseDir,
                    $selectedFolder,
                    $filename
                );
                $logs = $result->logs;
                $success = $result->success;
            } elseif ($action === 'apply_all_ready') {
                $continuation = $this->posted('intake_batch_continue') === '1';

                if (! $continuation) {
                    $this->saveAutoUpdateState([
                        'folder' => $selectedFolder,
                        'processed' => 0,
                        'updated' => 0,
                        'review' => 0,
                        'remaining' => 0,
                        'started_at' => $this->currentTime(),
                        'updated_at' => $this->currentTime(),
                        'complete' => false,
                    ]);
                }

                $batch = $this->scanner->applyReadyUpdates(
                    $uploadsBaseDir,
                    $selectedFolder,
                    ProductBatchIntakeScanner::MAX_AUTO_UPDATE_BATCH
                );
                $autoState = $this->accumulateAutoUpdateState(
                    $selectedFolder,
                    $batch
                );
                $logs = $batch['logs'];
                $success = $batch['failed'] === 0;
                $autoContinue = $batch['continue'];
            } elseif ($action === 'create') {
                $coordinator = new ProductBatchCreateCoordinator(
                    $this->call,
                    $this->scanner
                );
                $result = $coordinator->createDraft(
                    $uploadsBaseDir,
                    $selectedFolder,
                    $filename,
                    $itemReference
                );
                $logs = $result->logs;
                $success = $result->success;
            } elseif ($action === 'create_all_new') {
                $continuation = $this->posted('intake_create_continue') === '1';
                $service = $this->createAllService();

                if (! $continuation) {
                    $scanRows = $this->scanner->scan(
                        $uploadsBaseDir,
                        $selectedFolder
                    );
                    $prepared = $service->prepare(
                        $scanRows,
                        $this->postedNewReferences()
                    );

                    if ($prepared['entries'] === []) {
                        throw new \RuntimeException(
                            'No NEW PRODUCT rows have an Envato URL / Item ID.'
                        );
                    }

                    $autoCreateState = [
                        'folder' => $selectedFolder,
                        'pending' => $prepared['entries'],
                        'missing' => $prepared['missing'],
                        'processed' => 0,
                        'created' => 0,
                        'review' => 0,
                        'product_ids' => [],
                        'started_at' => $this->currentTime(),
                        'updated_at' => $this->currentTime(),
                        'complete' => false,
                    ];
                    $this->saveAutoCreateState($autoCreateState);
                } else {
                    $autoCreateState = $this->loadAutoCreateState();

                    if (
                        (string) ($autoCreateState['folder'] ?? '')
                        !== $selectedFolder
                    ) {
                        throw new \RuntimeException(
                            'AUTO CREATE state belongs to another INBOX folder.'
                        );
                    }
                }

                $pending = is_array($autoCreateState['pending'] ?? null)
                    ? $autoCreateState['pending']
                    : [];
                $batch = $service->process(
                    $uploadsBaseDir,
                    $selectedFolder,
                    $pending,
                    ProductBatchCreateAllService::MAX_BATCH
                );
                $autoCreateState = $this->accumulateAutoCreateState(
                    $selectedFolder,
                    $autoCreateState,
                    $batch
                );
                $logs = $batch['logs'];
                $success = $batch['failed'] === 0;
                $autoCreateContinue = $batch['continue'];
            } elseif ($action === 'skip') {
                $target = $this->scanner->moveToBucket(
                    $uploadsBaseDir,
                    $selectedFolder,
                    $filename,
                    '_SKIPPED'
                );
                $logs = [
                    'BATCH ACTION = SKIP',
                    'ZIP = ' . $filename,
                    'MOVED TO = ' . $target,
                    'NO PRODUCT WRITTEN = YES',
                ];
                $success = true;
            } elseif ($action === 'review') {
                $target = $this->scanner->moveToBucket(
                    $uploadsBaseDir,
                    $selectedFolder,
                    $filename,
                    '_REVIEW'
                );
                $logs = [
                    'BATCH ACTION = REVIEW',
                    'ZIP = ' . $filename,
                    'MOVED TO = ' . $target,
                    'NO PRODUCT WRITTEN = YES',
                ];
                $success = true;
            }

            if ($showResults) {
                $rows = $this->scanner->scan(
                    $uploadsBaseDir,
                    $selectedFolder
                );
            }

            if ($autoState === []) {
                $autoState = $this->loadAutoUpdateState();
            }

            if ($autoCreateState === []) {
                $autoCreateState = $this->loadAutoCreateState();
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $success = false;
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Import Queue</h1>';
        echo '<p>Пакетный вход для ZIP-архивов. UPDATE применяется прямо из очереди. Для нового товара Import Queue создаёт WooCommerce Draft, переносит архив в каноническую папку и заполняет доступные данные Envato. Draft всегда нужно проверить перед публикацией.</p>';

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>BATCH INTAKE ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderLogs($logs, $success);
        $this->renderAutoUpdateState($autoState, $selectedFolder);
        $this->renderAutoCreateState($autoCreateState, $selectedFolder);

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>INBOX ROOT</strong> = '
            . $this->escape($root !== '' ? $root : 'UNAVAILABLE')
            . '</p>';
        echo '<p>Загрузи ZIP-файлы с исходными именами в отдельную папку внутри INBOX. Для существующих товаров Item ID определяется по каталогу/ZIP. Для нового ZIP без Item ID достаточно один раз вставить ThemeForest/CodeCanyon URL или Item ID.</p>';
        echo '</div>';

        $this->renderScanForm($folders, $selectedFolder);

        if ($showResults && $error === '') {
            $this->renderSummary($rows, $selectedFolder);
            $this->renderApplyAllReady($rows, $selectedFolder);
            $this->renderCreateAllNew($rows, $selectedFolder);
            $this->renderTable($rows, $selectedFolder);

            if ($autoContinue) {
                $this->renderAutoContinue($selectedFolder);
            }

            if ($autoCreateContinue) {
                $this->renderAutoCreateContinue($selectedFolder);
            }
        }

        echo '</div>';
    }

    /** @param list<string> $folders */
    private function renderScanForm(array $folders, string $selectedFolder): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">1. Выбрать папку партии</h2>';
        echo '<form method="post" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="scan">';
        echo '<label><strong>INBOX folder</strong><br>';
        echo '<select name="intake_folder" style="min-width:320px;">';
        echo '<option value=""'
            . ($selectedFolder === '' ? ' selected' : '')
            . '>INBOX root</option>';

        foreach ($folders as $folder) {
            echo '<option value="'
                . $this->escapeAttr($folder)
                . '"'
                . ($selectedFolder === $folder ? ' selected' : '')
                . '>'
                . $this->escape($folder)
                . '</option>';
        }

        echo '</select></label>';
        echo '<button type="submit" class="button button-primary">Сканировать ZIP</button>';
        echo '</form>';

        if ($folders === []) {
            echo '<p style="margin-bottom:0;"><em>Подпапок пока нет. Можно загрузить ZIP прямо в INBOX root или создать папку партии через файловый менеджер/FTP.</em></p>';
        }

        echo '</div>';
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
     */
    private function renderApplyAllReady(
        array $rows,
        string $selectedFolder
    ): void {
        $ready = $this->scanner->readyUpdateRows($rows);

        if ($ready === []) {
            return;
        }

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Apply ALL READY Updates</h2>';
        echo '<p><strong>READY UPDATE = '
            . $this->escape((string) count($ready))
            . '</strong>. Обработка идёт автоматически партиями по '
            . $this->escape((string) ProductBatchIntakeScanner::MAX_AUTO_UPDATE_BATCH)
            . ' товаров за HTTP-запрос. После каждой партии страница сама продолжает следующую, пока READY UPDATE не закончатся.</p>';
        echo '<p>Только существующие товары со статусом <strong>UPDATE / READY</strong> обновляются автоматически. CREATE / REVIEW / STOP не затрагиваются. Ошибка отдельного ZIP переносит его в <code>_REVIEW</code> и не блокирует остальные товары.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="apply_all_ready">';
        echo '<input type="hidden" name="intake_batch_continue" value="0">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($selectedFolder)
            . '">';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Автоматически обновить все UPDATE / READY ZIP в этой папке? CREATE, REVIEW и STOP будут пропущены.\');">Apply ALL READY Updates</button>';
        echo '</form>';
        echo '</div>';
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
     */
    private function renderCreateAllNew(
        array $rows,
        string $selectedFolder
    ): void {
        $newRows = array_values(
            array_filter(
                $rows,
                static fn (array $row): bool => $row['productId'] <= 0
                    && $row['productType'] !== ''
            )
        );

        if ($newRows === []) {
            return;
        }

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Create ALL NEW Products as Drafts</h2>';
        echo '<p><strong>NEW PRODUCT = '
            . $this->escape((string) count($newRows))
            . '</strong>. Для ZIP без Item ID укажи Envato URL или Item ID. Пустые строки будут пропущены. Создаются только WooCommerce Drafts; публикация вручную после проверки.</p>';
        echo '<p>Обработка идёт автоматически партиями по '
            . $this->escape((string) ProductBatchCreateAllService::MAX_BATCH)
            . ' товаров за HTTP-запрос. Ошибка отдельного ZIP переносит его в <code>_REVIEW</code> и не блокирует остальные Drafts.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="create_all_new">';
        echo '<input type="hidden" name="intake_create_continue" value="0">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($selectedFolder)
            . '">';
        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr><th>ZIP</th><th>Type</th><th>Detected</th><th>Envato URL / Item ID</th></tr></thead><tbody>';

        foreach ($newRows as $row) {
            echo '<tr>';
            echo '<td><code>' . $this->escape($row['filename']) . '</code></td>';
            echo '<td>' . $this->escape($row['productType']) . '</td>';
            echo '<td>'
                . $this->escape(
                    $row['detectedVersion'] !== ''
                        ? $row['detectedVersion']
                        : '—'
                )
                . '</td>';
            echo '<td>';
            echo '<input type="hidden" name="intake_new_filename[]" value="'
                . $this->escapeAttr($row['filename'])
                . '">';

            if ($row['itemId'] > 0) {
                echo '<input type="hidden" name="intake_new_reference[]" value="'
                    . $this->escapeAttr((string) $row['itemId'])
                    . '">';
                echo '<code>' . $this->escape((string) $row['itemId']) . '</code>';
            } else {
                echo '<input type="text" name="intake_new_reference[]" placeholder="ThemeForest/CodeCanyon URL or Item ID" style="width:100%;max-width:520px;">';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-bottom:0;"><button type="submit" class="button button-primary" onclick="return confirm(\'Создать Draft для всех NEW PRODUCT, где указан Envato URL / Item ID? Товары не будут опубликованы автоматически.\');">Create ALL NEW Drafts</button></p>';
        echo '</form>';
        echo '</div>';
    }

    private function renderAutoCreateContinue(string $selectedFolder): void
    {
        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>AUTO CREATE CONTINUE = READY</strong> — следующая партия Drafts запустится автоматически.</p>';
        echo '</div>';
        echo '<form id="wp-shop-auto-create-continue" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="create_all_new">';
        echo '<input type="hidden" name="intake_create_continue" value="1">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($selectedFolder)
            . '">';
        echo '</form>';
        echo '<script>window.setTimeout(function(){var f=document.getElementById("wp-shop-auto-create-continue");if(f){f.submit();}},900);</script>';
    }

    private function createAllService(): ProductBatchCreateAllService
    {
        $coordinator = new ProductBatchCreateCoordinator(
            $this->call,
            $this->scanner
        );

        return new ProductBatchCreateAllService(
            static fn (
                string $uploadsBaseDir,
                string $folder,
                string $filename,
                string $reference
            ) => $coordinator->createDraft(
                $uploadsBaseDir,
                $folder,
                $filename,
                $reference
            ),
            fn (
                string $uploadsBaseDir,
                string $folder,
                string $filename
            ): string => $this->scanner->moveToBucket(
                $uploadsBaseDir,
                $folder,
                $filename,
                '_REVIEW'
            )
        );
    }

    /**
     * @return array<string, string>
     */
    private function postedNewReferences(): array
    {
        $filenames = $_POST['intake_new_filename'] ?? [];
        $references = $_POST['intake_new_reference'] ?? [];

        if (! is_array($filenames) || ! is_array($references)) {
            return [];
        }

        $result = [];
        $count = min(count($filenames), count($references));

        for ($index = 0; $index < $count; ++$index) {
            $rawFilename = $filenames[$index] ?? null;
            $rawReference = $references[$index] ?? null;

            if (! is_scalar($rawFilename) || ! is_scalar($rawReference)) {
                continue;
            }

            $filename = trim((string) ($this->call)(
                'sanitize_file_name',
                (string) ($this->call)(
                    'wp_unslash',
                    (string) $rawFilename
                )
            ));
            $reference = trim((string) ($this->call)(
                'sanitize_text_field',
                (string) ($this->call)(
                    'wp_unslash',
                    (string) $rawReference
                )
            ));

            if ($filename !== '') {
                $result[$filename] = $reference;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $state
     * @param array{
     *   processed:int,
     *   created:int,
     *   failed:int,
     *   productIds:list<int>,
     *   remaining:list<array{filename:string,reference:string}>,
     *   continue:bool,
     *   logs:list<string>
     * } $batch
     * @return array<string, mixed>
     */
    private function accumulateAutoCreateState(
        string $folder,
        array $state,
        array $batch
    ): array {
        if ((string) ($state['folder'] ?? '') !== $folder) {
            $state = [
                'folder' => $folder,
                'pending' => [],
                'missing' => [],
                'processed' => 0,
                'created' => 0,
                'review' => 0,
                'product_ids' => [],
                'started_at' => $this->currentTime(),
                'updated_at' => $this->currentTime(),
                'complete' => false,
            ];
        }

        $existingIds = is_array($state['product_ids'] ?? null)
            ? array_map('intval', $state['product_ids'])
            : [];
        $state['processed'] = (int) ($state['processed'] ?? 0)
            + $batch['processed'];
        $state['created'] = (int) ($state['created'] ?? 0)
            + $batch['created'];
        $state['review'] = (int) ($state['review'] ?? 0)
            + $batch['failed'];
        $state['product_ids'] = array_values(
            array_unique(
                array_merge(
                    $existingIds,
                    $batch['productIds']
                )
            )
        );
        $state['pending'] = $batch['remaining'];
        $state['updated_at'] = $this->currentTime();
        $state['complete'] = $batch['remaining'] === [];
        $this->saveAutoCreateState($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function renderAutoCreateState(
        array $state,
        string $selectedFolder
    ): void {
        if (
            $state === []
            || (string) ($state['folder'] ?? '') !== $selectedFolder
        ) {
            return;
        }

        $missing = is_array($state['missing'] ?? null)
            ? array_map('strval', $state['missing'])
            : [];
        $productIds = is_array($state['product_ids'] ?? null)
            ? array_map('intval', $state['product_ids'])
            : [];
        $pending = is_array($state['pending'] ?? null)
            ? count($state['pending'])
            : 0;

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>AUTO CREATE REPORT</strong> &nbsp; PROCESSED = '
            . $this->escape((string) ((int) ($state['processed'] ?? 0)))
            . ' &nbsp; DRAFT CREATED = '
            . $this->escape((string) ((int) ($state['created'] ?? 0)))
            . ' &nbsp; REVIEW = '
            . $this->escape((string) ((int) ($state['review'] ?? 0)))
            . ' &nbsp; REMAINING = '
            . $this->escape((string) $pending)
            . ' &nbsp; STATUS = '
            . $this->escape((bool) ($state['complete'] ?? false) ? 'COMPLETE' : 'RUNNING')
            . '</p>';

        if ($productIds !== []) {
            echo '<p>CREATED PRODUCT IDs = '
                . $this->escape(
                    implode(
                        ', ',
                        array_map('strval', $productIds)
                    )
                )
                . '</p>';
        }

        if ($missing !== []) {
            echo '<p>SKIPPED — ENVATO REFERENCE REQUIRED = '
                . $this->escape(implode(', ', $missing))
                . '</p>';
        }

        echo '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAutoCreateState(): array
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId <= 0) {
            return [];
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::AUTO_CREATE_STATE_META_KEY,
            true
        );

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveAutoCreateState(array $state): void
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::AUTO_CREATE_STATE_META_KEY,
                $state
            );
        }
    }

    private function renderAutoContinue(string $selectedFolder): void
    {
        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>AUTO BATCH CONTINUE = READY</strong> — следующая партия запустится автоматически.</p>';
        echo '</div>';
        echo '<form id="wp-shop-auto-batch-continue" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="apply_all_ready">';
        echo '<input type="hidden" name="intake_batch_continue" value="1">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($selectedFolder)
            . '">';
        echo '</form>';
        echo '<script>window.setTimeout(function(){var f=document.getElementById("wp-shop-auto-batch-continue");if(f){f.submit();}},900);</script>';
    }

    /**
     * @param array{
     *   processed: int,
     *   updated: int,
     *   failed: int,
     *   remaining: int,
     *   continue: bool,
     *   logs: list<string>
     * } $batch
     * @return array<string, mixed>
     */
    private function accumulateAutoUpdateState(
        string $folder,
        array $batch
    ): array {
        $state = $this->loadAutoUpdateState();

        if (
            (string) ($state['folder'] ?? '') !== $folder
            || (bool) ($state['complete'] ?? false)
        ) {
            $state = [
                'folder' => $folder,
                'processed' => 0,
                'updated' => 0,
                'review' => 0,
                'remaining' => 0,
                'started_at' => $this->currentTime(),
                'updated_at' => $this->currentTime(),
                'complete' => false,
            ];
        }

        $state['processed'] = (int) ($state['processed'] ?? 0)
            + $batch['processed'];
        $state['updated'] = (int) ($state['updated'] ?? 0)
            + $batch['updated'];
        $state['review'] = (int) ($state['review'] ?? 0)
            + $batch['failed'];
        $state['remaining'] = $batch['remaining'];
        $state['updated_at'] = $this->currentTime();
        $state['complete'] = $batch['remaining'] === 0;
        $this->saveAutoUpdateState($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function renderAutoUpdateState(
        array $state,
        string $selectedFolder
    ): void {
        if (
            $state === []
            || (string) ($state['folder'] ?? '') !== $selectedFolder
        ) {
            return;
        }

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>AUTO UPDATE REPORT</strong> &nbsp; PROCESSED = '
            . $this->escape((string) ((int) ($state['processed'] ?? 0)))
            . ' &nbsp; UPDATED = '
            . $this->escape((string) ((int) ($state['updated'] ?? 0)))
            . ' &nbsp; REVIEW = '
            . $this->escape((string) ((int) ($state['review'] ?? 0)))
            . ' &nbsp; REMAINING READY = '
            . $this->escape((string) ((int) ($state['remaining'] ?? 0)))
            . ' &nbsp; STATUS = '
            . $this->escape((bool) ($state['complete'] ?? false) ? 'COMPLETE' : 'RUNNING')
            . '</p></div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAutoUpdateState(): array
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId <= 0) {
            return [];
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::AUTO_UPDATE_STATE_META_KEY,
            true
        );

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveAutoUpdateState(array $state): void
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::AUTO_UPDATE_STATE_META_KEY,
                $state
            );
        }
    }

    private function currentTime(): string
    {
        return (string) ($this->call)('current_time', 'mysql');
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
     */
    private function renderSummary(array $rows, string $folder): void
    {
        $update = 0;
        $create = 0;
        $review = 0;
        $ready = 0;

        foreach ($rows as $row) {
            if ($row['action'] === 'UPDATE') {
                $update++;
            } elseif ($row['action'] === 'CREATE') {
                $create++;
            } else {
                $review++;
            }

            if ($row['status'] === 'READY') {
                $ready++;
            }
        }

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>BATCH SCAN = READY</strong> &nbsp; FOLDER = '
            . $this->escape($folder !== '' ? $folder : 'INBOX root')
            . ' &nbsp; ZIP = ' . $this->escape((string) count($rows))
            . ' &nbsp; READY = ' . $this->escape((string) $ready)
            . ' &nbsp; UPDATE = ' . $this->escape((string) $update)
            . ' &nbsp; CREATE = ' . $this->escape((string) $create)
            . ' &nbsp; REVIEW = ' . $this->escape((string) $review)
            . '</p></div>';
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
     */
    private function renderTable(array $rows, string $selectedFolder): void
    {
        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">2. Проверка партии</h2>';

        if ($rows === []) {
            echo '<p><strong>BATCH FOLDER = COMPLETE / EMPTY</strong></p>';
            echo '<p><em>Активных ZIP-файлов в выбранной папке больше нет.</em></p>';
            echo '</div>';

            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';

        foreach (
            [
                'ZIP',
                'Item ID',
                'Product',
                'Type',
                'Current',
                'Detected',
                'Action',
                'Status',
                'Note',
                'Controls',
            ] as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $product = $row['productId'] > 0
                ? '#' . $row['productId'] . ' ' . $row['productTitle']
                : 'NEW PRODUCT';

            echo '<tr>';
            echo '<td><code>' . $this->escape($row['relativePath']) . '</code></td>';
            echo '<td>' . $this->escape($row['itemId'] > 0 ? (string) $row['itemId'] : '—') . '</td>';
            echo '<td>' . $this->escape($product) . '</td>';
            echo '<td>' . $this->escape($row['productType'] !== '' ? $row['productType'] : '—') . '</td>';
            echo '<td>' . $this->escape($row['currentVersion'] !== '' ? $row['currentVersion'] : '—') . '</td>';
            echo '<td>' . $this->escape($row['detectedVersion'] !== '' ? $row['detectedVersion'] : '—') . '</td>';
            echo '<td><strong>' . $this->escape($row['action']) . '</strong></td>';
            echo '<td><strong>' . $this->escape($row['status']) . '</strong></td>';
            echo '<td>' . $this->escape($row['note']) . '</td>';
            echo '<td style="min-width:320px;">';
            $this->renderRowControls($row, $selectedFolder);
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-bottom:0;margin-top:14px;"><strong>Safety:</strong> UPDATE выполняет внутренний Preflight и rollback. CREATE создаёт только Draft, сверяет ZIP с Envato, не назначает Hit/New автоматически и удаляет исходник из INBOX только после успешного создания.</p>';
        echo '</div>';
    }

    /**
     * @param array{
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
     * } $row
     */
    private function renderRowControls(array $row, string $selectedFolder): void
    {
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';

        if ($row['action'] === 'UPDATE' && $row['status'] === 'READY') {
            $this->actionButton(
                'apply',
                'Apply Update',
                'button button-primary',
                $row['filename'],
                $selectedFolder,
                "Применить обновление к товару #{$row['productId']}?"
            );
        } elseif (
            $row['productId'] <= 0
            && $row['productType'] !== ''
        ) {
            $this->createDraftForm($row, $selectedFolder);
        }

        $this->actionButton(
            'review',
            'Review',
            'button button-secondary',
            $row['filename'],
            $selectedFolder,
            ''
        );
        $this->actionButton(
            'skip',
            'Skip',
            'button',
            $row['filename'],
            $selectedFolder,
            ''
        );
        echo '</div>';
    }

    /**
     * @param array{
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
     * } $row
     */
    private function createDraftForm(array $row, string $folder): void
    {
        echo '<form method="post" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="create">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($folder)
            . '">';
        echo '<input type="hidden" name="intake_filename" value="'
            . $this->escapeAttr($row['filename'])
            . '">';

        if ($row['itemId'] > 0) {
            echo '<input type="hidden" name="intake_item_reference" value="'
                . $this->escapeAttr((string) $row['itemId'])
                . '">';
        } else {
            echo '<input type="text" name="intake_item_reference" required placeholder="Envato URL / Item ID" style="width:210px;">';
        }

        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Создать WooCommerce Draft из этого ZIP? Draft нужно проверить перед публикацией.\');">Create Draft</button>';
        echo '</form>';
    }

    private function actionButton(
        string $action,
        string $label,
        string $class,
        string $filename,
        string $folder,
        string $confirm
    ): void {
        echo '<form method="post" style="margin:0;display:inline;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_batch_intake_action" value="'
            . $this->escapeAttr($action)
            . '">';
        echo '<input type="hidden" name="intake_folder" value="'
            . $this->escapeAttr($folder)
            . '">';
        echo '<input type="hidden" name="intake_filename" value="'
            . $this->escapeAttr($filename)
            . '">';
        echo '<button type="submit" class="'
            . $this->escapeAttr($class)
            . '"';

        if ($confirm !== '') {
            echo ' onclick="return confirm(\''
                . $this->escapeAttr($confirm)
                . '\');"';
        }

        echo '>' . $this->escape($label) . '</button>';
        echo '</form>';
    }

    /** @param list<string> $logs */
    private function renderLogs(array $logs, ?bool $success): void
    {
        if ($logs === []) {
            return;
        }

        $color = $success === true ? '#00a32a' : '#d63638';
        echo '<div style="max-width:1500px;background:#fff;border-left:4px solid '
            . $this->escapeAttr($color)
            . ';padding:12px 16px;margin:15px 0 20px;">';
        echo '<strong>BATCH INTAKE LOG</strong>';
        echo '<pre style="white-space:pre-wrap;margin-bottom:0;">'
            . $this->escape(implode("\n", $logs))
            . '</pre>';
        echo '</div>';
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_batch_intake_scan',
            '_wpnonce',
            true,
            true
        );
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_batch_intake_scan'
        );
    }

    private function posted(string $key): string
    {
        if (! isset($_POST[$key]) || ! is_scalar($_POST[$key])) {
            return '';
        }

        return trim((string) ($this->call)(
            'sanitize_text_field',
            (string) $_POST[$key]
        ));
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }

    private function escapeAttr(string $value): string
    {
        return (string) ($this->call)('esc_attr', $value);
    }
}
