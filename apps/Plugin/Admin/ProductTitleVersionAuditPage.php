<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionAuditRow;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionAuditService;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionMigrationService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductTitleVersionAuditPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_title_version_audit_report_v1';
    private const STATE_META_KEY = 'wp_shop_pm_title_version_audit_state_v1';
    private const MIGRATION_META_KEY = 'wp_shop_pm_title_version_migration_v1';
    private const MIGRATION_STATE_META_KEY = 'wp_shop_pm_title_version_migration_state_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductTitleVersionAuditService $audit,
        private readonly ProductTitleVersionMigrationService $migration,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-title-version-audit';
    }

    public function title(): string
    {
        return 'Product Title Version Audit';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_title_version_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;
        $migrationMessage = '';
        $migrationError = '';
        $migrationAutoContinue = false;

        if ($action === 'audit_start') {
            $this->checkNonce();
            $limit = $this->limit(
                (int) $this->posted('audit_limit', '50')
            );
            $this->resetReport();
            $state = $this->newState(
                $limit,
                $this->audit->candidateCount()
            );
            $this->saveState($state);
            [$state, $message, $error] = $this->processNextBatch($state);
            $autoContinue = $error === '' && $state['status'] === 'RUNNING';
        } elseif (
            $action === 'audit_next'
            || $action === 'audit_resume'
        ) {
            $this->checkNonce();

            if ($state['status'] !== 'RUNNING') {
                $error = 'No running Product Title Version Audit was found.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        } elseif ($action === 'migration_start') {
            $this->checkNonce();

            if ($state['status'] !== 'READY') {
                $migrationError = 'Run and finish Product Title Version Audit before applying migration.';
            } else {
                $report = $this->loadReport();
                $candidates = $this->removalRows($report['rows']);
                $this->resetMigration($candidates);
                $migrationState = $this->newMigrationState(
                    count($candidates)
                );
                $this->saveMigrationState($migrationState);
                [
                    $migrationState,
                    $migrationMessage,
                    $migrationError,
                ] = $this->processMigrationBatch($migrationState);
                $migrationAutoContinue = $migrationError === ''
                    && $migrationState['status'] === 'RUNNING';
            }
        } elseif (
            $action === 'migration_next'
            || $action === 'migration_resume'
        ) {
            $this->checkNonce();
            $migrationState = $this->loadMigrationState();

            if ($migrationState['status'] !== 'RUNNING') {
                $migrationError = 'No running Safe Version Removal migration was found.';
            } else {
                [
                    $migrationState,
                    $migrationMessage,
                    $migrationError,
                ] = $this->processMigrationBatch($migrationState);
                $migrationAutoContinue = $migrationError === ''
                    && $migrationState['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->summary($report);
        $attention = $this->attentionRows($report['rows']);
        $migrationState = $this->loadMigrationState();
        $migrationReport = $this->loadMigration();
        $migrationSummary = $this->migrationSummary($migrationReport);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Product Title Version Audit</h1>';
        echo '<p><strong>DRY RUN ONLY.</strong> Scans all published products regardless of source. A title is marked REMOVE_VERSION only when it ends with the exact stored <code>attr_version_value</code> (or <code>v</code> + exact stored version). No product data is written.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>TITLE VERSION AUDIT ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        if ($migrationMessage !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($migrationMessage)
                . '</strong></p></div>';
        }

        if ($migrationError !== '') {
            echo '<div class="notice notice-error"><p><strong>SAFE VERSION REMOVAL ERROR:</strong> '
                . $this->escape($migrationError)
                . '</p></div>';
        }

        $this->renderProgress($state, $summary);
        $this->renderControls($state);
        $this->renderMigrationControls(
            $state,
            $migrationState,
            $migrationSummary
        );

        if ($state['status'] === 'READY') {
            $this->renderExport();
        }

        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">REMOVE_VERSION / REVIEW</h2>';

        if ($attention === []) {
            echo '<p><strong>No title-version changes found in the saved report.</strong></p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach (
                [
                    'ID',
                    'Source',
                    'Current title',
                    'Stored version',
                    'Recommended title',
                    'Action',
                    'Reason',
                    'Product',
                ]
                as $heading
            ) {
                echo '<th>' . $this->escape($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($attention as $row) {
                $productId = (int) ($row['productId'] ?? 0);
                echo '<tr>';
                echo '<td>' . $this->escape((string) $productId) . '</td>';
                echo '<td><strong>' . $this->escape((string) ($row['source'] ?? '')) . '</strong></td>';
                echo '<td>' . $this->escape((string) ($row['currentTitle'] ?? '')) . '</td>';
                echo '<td><code>' . $this->escape((string) ($row['storedVersion'] ?? '')) . '</code></td>';
                echo '<td><strong>' . $this->escape((string) ($row['recommendedTitle'] ?? '')) . '</strong></td>';
                echo '<td><strong>' . $this->escape((string) ($row['action'] ?? '')) . '</strong></td>';
                echo '<td>' . $this->escape((string) ($row['reason'] ?? '')) . '</td>';
                echo '<td>';
                $editUrl = (string) ($this->call)(
                    'get_edit_post_link',
                    $productId,
                    'raw'
                );
                if ($editUrl !== '') {
                    echo '<a class="button button-secondary" href="'
                        . $this->escapeUrl($editUrl)
                        . '">Edit product</a>';
                } else {
                    echo '—';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';

        if ($migrationSummary['total'] > 0) {
            $this->renderMigrationExceptions($migrationReport);
        }

        if ($autoContinue) {
            $this->renderAutoContinue();
        }

        if ($migrationAutoContinue) {
            $this->renderMigrationAutoContinue();
        }

        echo '</div>';
    }

    public function exportCsv(): void
    {
        if (! (bool) ($this->call)('current_user_can', $this->capability())) {
            ($this->call)('wp_die', 'You are not allowed to export this report.');

            return;
        }

        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_export_title_version_audit',
            '_wpnonce'
        );

        $report = $this->loadReport();
        $rows = $this->allRows($report['rows']);
        $filename = 'wp-shop-product-title-version-audit-'
            . (string) ($this->call)('current_time', 'Y-m-d-His')
            . '.csv';

        ($this->call)('nocache_headers');
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="'
            . $filename
            . '"'
        );
        header('X-Content-Type-Options: nosniff');

        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            ($this->call)('wp_die', 'Unable to open CSV output stream.');

            return;
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            [
                'Product ID',
                'Source',
                'Current Title',
                'Stored Version',
                'Recommended Title',
                'Action',
                'Reason',
            ],
            ';',
            '"',
            ''
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                [
                    (string) ($row['productId'] ?? ''),
                    (string) ($row['source'] ?? ''),
                    (string) ($row['currentTitle'] ?? ''),
                    (string) ($row['storedVersion'] ?? ''),
                    (string) ($row['recommendedTitle'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                ],
                ';',
                '"',
                ''
            );
        }

        fclose($stream);
        exit;
    }

    /**
     * @param array<string, int|string> $state
     * @return array{array<string, int|string>, string, string}
     */
    private function processNextBatch(array $state): array
    {
        $offset = (int) $state['next_offset'];
        $limit = $this->limit((int) $state['limit']);

        try {
            $rows = $this->audit->scan($offset, $limit);
            $this->saveReportRows($rows);
        } catch (Throwable $exception) {
            $state['status'] = 'FAILED';
            $state['error'] = $exception->getMessage();
            $state['updated_at'] = $this->currentTime();
            $this->saveState($state);

            return [$state, '', $exception->getMessage()];
        }

        $report = $this->loadReport();
        $state['processed'] = count($report['seen']);
        $state['next_offset'] = $offset + $limit;
        $state['updated_at'] = $this->currentTime();
        $state['error'] = '';

        $finished = count($rows) < $limit
            || $state['processed'] >= (int) $state['total'];

        if ($finished) {
            $state['status'] = 'READY';
            $state['processed'] = min(
                (int) $state['processed'],
                (int) $state['total']
            );
            $message = 'PRODUCT TITLE VERSION AUDIT = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'PRODUCT TITLE VERSION AUDIT BATCH = SAVED';
        }

        $this->saveState($state);

        return [$state, $message, ''];
    }

    /**
     * @param list<ProductTitleVersionAuditRow> $rows
     */
    private function saveReportRows(array $rows): void
    {
        $report = $this->loadReport();

        if ($report['started_at'] === '') {
            $report['started_at'] = $this->currentTime();
        }

        foreach ($rows as $row) {
            $id = $row->productId;
            $report['seen'][$id] = $row->action;
            $report['rows'][$id] = [
                'productId' => $row->productId,
                'source' => $row->source,
                'currentTitle' => $row->currentTitle,
                'storedVersion' => $row->storedVersion,
                'recommendedTitle' => $row->recommendedTitle,
                'action' => $row->action,
                'reason' => $row->reason,
            ];
        }

        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
    }

    /**
     * @param array<string, mixed> $report
     * @return array{scanned:int,remove:int,keep:int,review:int}
     */
    private function summary(array $report): array
    {
        $summary = [
            'scanned' => count($report['seen']),
            'remove' => 0,
            'keep' => 0,
            'review' => 0,
        ];

        foreach ($report['seen'] as $action) {
            if ($action === 'REMOVE_VERSION') {
                ++$summary['remove'];
            } elseif ($action === 'REVIEW') {
                ++$summary['review'];
            } else {
                ++$summary['keep'];
            }
        }

        return $summary;
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function allRows(array $stored): array
    {
        $rows = [];

        foreach ($stored as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        usort(
            $rows,
            static fn (array $left, array $right): int =>
                (int) ($left['productId'] ?? 0)
                <=> (int) ($right['productId'] ?? 0)
        );

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function attentionRows(array $stored): array
    {
        return array_values(array_filter(
            $this->allRows($stored),
            static fn (array $row): bool =>
                (string) ($row['action'] ?? '') !== 'KEEP'
        ));
    }

    /**
     * @param array<string, int|string> $state
     * @param array{scanned:int,remove:int,keep:int,review:int} $summary
     */
    private function renderProgress(array $state, array $summary): void
    {
        $total = (int) $state['total'];
        $processed = (int) $state['processed'];
        $percent = $total > 0
            ? min(100, (int) floor(($processed / $total) * 100))
            : ($state['status'] === 'READY' ? 100 : 0);

        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>TITLE VERSION AUDIT = '
            . $this->escape((string) $state['status'])
            . '</strong> &nbsp; PROCESSED = '
            . $this->escape((string) $processed)
            . ' &nbsp; TOTAL = '
            . $this->escape((string) $total)
            . ' &nbsp; PROGRESS = '
            . $this->escape((string) $percent)
            . '%</p>';
        echo '<p><strong>PRODUCT WRITES = 0</strong> &nbsp; REMOVE_VERSION = '
            . $this->escape((string) $summary['remove'])
            . ' &nbsp; KEEP = '
            . $this->escape((string) $summary['keep'])
            . ' &nbsp; REVIEW = '
            . $this->escape((string) $summary['review'])
            . '</p>';
        echo '</div>';
    }

    /**
     * @param array<string, int|string> $state
     */
    private function renderControls(array $state): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Full catalog version suffix audit</h2>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="audit_start">';
        echo '<p><label><strong>Batch size</strong><br><input type="number" min="1" max="100" name="audit_limit" value="'
            . $this->escapeAttr((string) $state['limit'])
            . '" style="width:180px;"></label></p>';
        echo '<button type="submit" class="button button-primary">Запустить Product Title Version Audit — без записи</button>';
        echo '</form>';

        if ($state['status'] === 'RUNNING') {
            echo '<form method="post" style="margin-top:12px;">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="audit_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Version Audit</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, int|string> $auditState
     * @param array<string, int|string> $migrationState
     * @param array{total:int,updated:int,skip:int,error:int} $summary
     */
    private function renderMigrationControls(
        array $auditState,
        array $migrationState,
        array $summary
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Safe Version Removal</h2>';
        echo '<p>Writes <strong>post_title only</strong> for saved REMOVE_VERSION candidates. Before every write the current title and <code>attr_version_value</code> are rechecked against the audit snapshot. Slug is explicitly preserved and verified after the write. Migration snapshot and log are stored only in this administrator\'s user meta.</p>';

        if ($auditState['status'] !== 'READY') {
            echo '<p><strong>Run Product Title Version Audit to READY first.</strong></p>';
            echo '</div>';

            return;
        }

        echo '<p><strong>MIGRATION = '
            . $this->escape((string) $migrationState['status'])
            . '</strong> &nbsp; TOTAL = '
            . $this->escape((string) $summary['total'])
            . ' &nbsp; UPDATED = '
            . $this->escape((string) $summary['updated'])
            . ' &nbsp; SKIP = '
            . $this->escape((string) $summary['skip'])
            . ' &nbsp; ERROR = '
            . $this->escape((string) $summary['error'])
            . '</p>';

        if ($migrationState['status'] !== 'RUNNING') {
            echo '<form method="post">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="migration_start">';
            echo '<button type="submit" class="button button-primary">Apply Safe Version Removal</button>';
            echo '</form>';
        } else {
            echo '<form method="post">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="migration_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Safe Version Removal</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $migration
     */
    private function renderMigrationExceptions(array $migration): void
    {
        $exceptions = [];

        foreach ((array) ($migration['results'] ?? []) as $row) {
            if (
                is_array($row)
                && (string) ($row['status'] ?? '') !== 'UPDATED'
            ) {
                $exceptions[] = $row;
            }
        }

        if ($exceptions === []) {
            return;
        }

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Migration SKIP / ERROR</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['ID', 'Status', 'Old title', 'New title', 'Slug', 'Reason'] as $heading) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($exceptions as $row) {
            echo '<tr>';
            echo '<td>' . $this->escape((string) ($row['productId'] ?? '')) . '</td>';
            echo '<td><strong>' . $this->escape((string) ($row['status'] ?? '')) . '</strong></td>';
            echo '<td>' . $this->escape((string) ($row['oldTitle'] ?? '')) . '</td>';
            echo '<td>' . $this->escape((string) ($row['newTitle'] ?? '')) . '</td>';
            echo '<td><code>' . $this->escape((string) ($row['slug'] ?? '')) . '</code></td>';
            echo '<td>' . $this->escape((string) ($row['reason'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * @param array<string, int|string> $state
     * @return array{array<string, int|string>, string, string}
     */
    private function processMigrationBatch(array $state): array
    {
        $migration = $this->loadMigration();
        $candidates = is_array($migration['candidates'] ?? null)
            ? array_values($migration['candidates'])
            : [];
        $offset = (int) $state['next_offset'];
        $batch = array_slice($candidates, $offset, 25);

        foreach ($batch as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            try {
                $result = $this->migration->apply($candidate);
            } catch (Throwable $exception) {
                $result = [
                    'productId' => (int) ($candidate['productId'] ?? 0),
                    'status' => 'ERROR',
                    'oldTitle' => (string) ($candidate['currentTitle'] ?? ''),
                    'newTitle' => (string) ($candidate['currentTitle'] ?? ''),
                    'slug' => '',
                    'reason' => $exception->getMessage(),
                ];
            }

            $productId = (int) ($result['productId'] ?? 0);

            if ($productId > 0) {
                $migration['results'][$productId] = $result;
            }
        }

        $state['processed'] = count((array) ($migration['results'] ?? []));
        $state['next_offset'] = $offset + count($batch);
        $state['updated_at'] = $this->currentTime();
        $state['error'] = '';

        $finished = $batch === []
            || $state['next_offset'] >= count($candidates);

        if ($finished) {
            $state['status'] = 'READY';
            $message = 'SAFE VERSION REMOVAL = READY';
        } else {
            $state['status'] = 'RUNNING';
            $message = 'SAFE VERSION REMOVAL BATCH = SAVED';
        }

        $migration['updated_at'] = $this->currentTime();
        $this->saveMigration($migration);
        $this->saveMigrationState($state);

        return [$state, $message, ''];
    }

    /**
     * @param array<int|string, mixed> $stored
     * @return list<array<string, mixed>>
     */
    private function removalRows(array $stored): array
    {
        return array_values(array_filter(
            $this->allRows($stored),
            static fn (array $row): bool =>
                (string) ($row['action'] ?? '') === 'REMOVE_VERSION'
        ));
    }

    /**
     * @param list<array<string, mixed>> $candidates
     */
    private function resetMigration(array $candidates): void
    {
        $this->saveMigration([
            'candidates' => $candidates,
            'results' => [],
            'started_at' => $this->currentTime(),
            'updated_at' => $this->currentTime(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMigration(): array
    {
        $empty = [
            'candidates' => [],
            'results' => [],
            'started_at' => '',
            'updated_at' => '',
        ];
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $empty;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::MIGRATION_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $empty;
        }

        foreach (['candidates', 'results'] as $key) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        foreach (['started_at', 'updated_at'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        return $empty;
    }

    /**
     * @param array<string, mixed> $migration
     */
    private function saveMigration(array $migration): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::MIGRATION_META_KEY,
                $migration
            );
        }
    }

    /**
     * @return array{total:int,updated:int,skip:int,error:int}
     */
    private function migrationSummary(array $migration): array
    {
        $summary = [
            'total' => count((array) ($migration['candidates'] ?? [])),
            'updated' => 0,
            'skip' => 0,
            'error' => 0,
        ];

        foreach ((array) ($migration['results'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $status = (string) ($row['status'] ?? '');

            if ($status === 'UPDATED') {
                ++$summary['updated'];
            } elseif ($status === 'ERROR') {
                ++$summary['error'];
            } elseif ($status === 'SKIP') {
                ++$summary['skip'];
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int|string>
     */
    private function newMigrationState(int $total): array
    {
        $now = $this->currentTime();

        return [
            'status' => $total === 0 ? 'READY' : 'RUNNING',
            'total' => max(0, $total),
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => $now,
            'updated_at' => $now,
            'error' => '',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function loadMigrationState(): array
    {
        $state = [
            'status' => 'IDLE',
            'total' => 0,
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => '',
            'updated_at' => '',
            'error' => '',
        ];
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $state;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::MIGRATION_STATE_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $state;
        }

        foreach (['status', 'started_at', 'updated_at', 'error'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $state[$key] = $stored[$key];
            }
        }

        foreach (['total', 'processed', 'next_offset'] as $key) {
            if (isset($stored[$key])) {
                $state[$key] = max(0, (int) $stored[$key]);
            }
        }

        return $state;
    }

    /**
     * @param array<string, int|string> $state
     */
    private function saveMigrationState(array $state): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::MIGRATION_STATE_META_KEY,
                $state
            );
        }
    }

    private function renderMigrationAutoContinue(): void
    {
        echo '<form id="wp-shop-title-version-migration-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="migration_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-title-version-migration-next");if(f){f.submit();}},900);';
        echo '</script>';
    }

    private function renderExport(): void
    {
        $action = (string) ($this->call)(
            'admin_url',
            'admin-post.php'
        );

        echo '<form method="post" action="'
            . $this->escapeUrl($action)
            . '" style="margin:12px 0;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_export_title_version_audit',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="wp_shop_pm_export_title_version_audit">';
        echo '<button type="submit" class="button button-secondary">Export Product Title Version CSV</button>';
        echo '</form>';
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-title-version-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_title_version_action" value="audit_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-title-version-next");if(f){f.submit();}},900);';
        echo '</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(): array
    {
        return [
            'seen' => [],
            'rows' => [],
            'started_at' => '',
            'updated_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(): array
    {
        $report = $this->emptyReport();
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $report;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::REPORT_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $report;
        }

        foreach (['seen', 'rows'] as $key) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $report[$key] = $stored[$key];
            }
        }

        foreach (['started_at', 'updated_at'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $report[$key] = $stored[$key];
            }
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function saveReport(array $report): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::REPORT_META_KEY,
                $report
            );
        }
    }

    private function resetReport(): void
    {
        $this->saveReport($this->emptyReport());
    }

    /**
     * @return array<string, int|string>
     */
    private function newState(int $limit, int $total): array
    {
        $now = $this->currentTime();

        return [
            'status' => $total === 0 ? 'READY' : 'RUNNING',
            'limit' => $limit,
            'total' => max(0, $total),
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => $now,
            'updated_at' => $now,
            'error' => '',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function loadState(): array
    {
        $state = [
            'status' => 'IDLE',
            'limit' => 50,
            'total' => 0,
            'processed' => 0,
            'next_offset' => 0,
            'started_at' => '',
            'updated_at' => '',
            'error' => '',
        ];
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return $state;
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::STATE_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $state;
        }

        foreach (['status', 'started_at', 'updated_at', 'error'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $state[$key] = $stored[$key];
            }
        }

        foreach (['limit', 'total', 'processed', 'next_offset'] as $key) {
            if (isset($stored[$key])) {
                $state[$key] = max(0, (int) $stored[$key]);
            }
        }

        $state['limit'] = $this->limit((int) $state['limit']);

        return $state;
    }

    /**
     * @param array<string, int|string> $state
     */
    private function saveState(array $state): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::STATE_META_KEY,
                $state
            );
        }
    }

    private function limit(int $limit): int
    {
        return max(1, min(100, $limit));
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_title_version_audit',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_title_version_audit',
            '_wpnonce',
            true,
            true
        );
    }

    private function currentUserId(): int
    {
        return (int) ($this->call)('get_current_user_id');
    }

    private function currentTime(): string
    {
        return (string) ($this->call)('current_time', 'mysql');
    }

    private function posted(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        if (! is_string($value)) {
            return $default;
        }

        return (string) ($this->call)('wp_unslash', $value);
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }

    private function escapeAttr(string $value): string
    {
        return (string) ($this->call)('esc_attr', $value);
    }

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
