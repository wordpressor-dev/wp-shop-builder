<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchCreateCoordinator;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductBatchIntakePage implements SubmenuPageInterface
{
    private const AUTO_UPDATE_STATE_META_KEY = 'wp_shop_pm_import_auto_update_v1';

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
        $autoState = [];

        try {
            $root = $this->scanner->ensureInbox($uploadsBaseDir);
            $folders = $this->scanner->folders($uploadsBaseDir);

            if (
                in_array(
                    $action,
                    ['scan', 'apply', 'apply_all_ready', 'create', 'skip', 'review'],
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
            $this->renderTable($rows, $selectedFolder);

            if ($autoContinue) {
                $this->renderAutoContinue($selectedFolder);
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
