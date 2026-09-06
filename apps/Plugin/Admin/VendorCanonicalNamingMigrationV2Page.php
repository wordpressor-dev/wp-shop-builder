<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Naming\VendorCanonicalNamingMigrationV2Service;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class VendorCanonicalNamingMigrationV2Page implements SubmenuPageInterface
{
    private const REPORT_META_KEY =
        'wp_shop_pm_vendor_canonical_naming_migration_v2';
    private const STATE_META_KEY =
        'wp_shop_pm_vendor_canonical_naming_migration_state_v2';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorCanonicalNamingMigrationV2Service $migration,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-vendor-canonical-naming-migration-v2';
    }

    public function title(): string
    {
        return 'Vendor Canonical Naming Migration V2';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_vendor_canonical_v2_action');
        $state = $this->loadState();
        $message = '';
        $error = '';
        $autoContinue = false;

        if ($action === 'migration_start') {
            $this->checkNonce();
            $this->resetReport();
            $state = $this->newState(
                count($this->migration->targets())
            );
            $this->saveState($state);
            [$state, $message, $error] = $this->processNextBatch($state);
            $autoContinue = $error === '' && $state['status'] === 'RUNNING';
        } elseif (
            $action === 'migration_next'
            || $action === 'migration_resume'
        ) {
            $this->checkNonce();

            if ($state['status'] !== 'RUNNING') {
                $error = 'No running Vendor Canonical Naming Migration V2 was found.';
            } else {
                [$state, $message, $error] = $this->processNextBatch($state);
                $autoContinue = $error === '' && $state['status'] === 'RUNNING';
            }
        }

        $report = $this->loadReport();
        $summary = $this->summary($report);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Vendor Canonical Naming Migration V2</h1>';
        echo '<p><strong>APPROVED MANIFEST = 11 PRODUCTS.</strong> This second migration changes only 11 independently verified Vendor H1 values. It preserves the slug, rechecks Vendor classification and Sales Page, validates current H1, and requires an exact TranslatePress EN title after every write. Two known malformed EN title strings (JetWooBuilder and YOOtheme Pro) are accepted only when they exactly match the V5 snapshot; any drift stops the row. If TranslatePress remap fails, the H1 is rolled back.</p>';
        echo '<p><strong>NOT INCLUDED:</strong> AutoPoly remains manual; wpDiscuz remains blocked by TYPE_MISMATCH; Code Snippets Pro has an EN-title integrity issue but no H1 rename and will be repaired separately.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>VENDOR CANONICAL NAMING MIGRATION V2 ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        echo '<div class="notice notice-info" style="max-width:1600px;padding:10px 14px;">';
        echo '<p><strong>MIGRATION = '
            . $this->escape((string) $state['status'])
            . '</strong> &nbsp; TOTAL = '
            . $this->escape((string) $summary['total'])
            . ' &nbsp; UPDATED = '
            . $this->escape((string) $summary['updated'])
            . ' &nbsp; SKIP = '
            . $this->escape((string) $summary['skip'])
            . ' &nbsp; ERROR = '
            . $this->escape((string) $summary['error'])
            . '</p>';
        echo '</div>';

        $this->renderTargets();
        $this->renderControls($state);
        $this->renderExceptions($report);

        if ($autoContinue) {
            $this->renderAutoContinue();
        }

        echo '</div>';
    }

    private function renderTargets(): void
    {
        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Approved canonical targets</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['ID', 'Current H1', 'Canonical H1', 'Sales Page'] as $heading) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($this->migration->targets() as $productId => $target) {
            echo '<tr>';
            echo '<td>' . $this->escape((string) $productId) . '</td>';
            echo '<td>' . $this->escape($target['from']) . '</td>';
            echo '<td><strong>' . $this->escape($target['to']) . '</strong></td>';
            echo '<td><code>' . $this->escape($target['sales_page']) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * @param array<string, int|string> $state
     */
    private function renderControls(array $state): void
    {
        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Apply approved migration</h2>';

        if ($state['status'] !== 'RUNNING') {
            echo '<form method="post">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_vendor_canonical_v2_action" value="migration_start">';
            echo '<button type="submit" class="button button-primary">Apply TranslatePress-Aware Vendor Canonical Migration V2</button>';
            echo '</form>';
        } else {
            echo '<form method="post">';
            $this->nonceField();
            echo '<input type="hidden" name="wp_shop_pm_vendor_canonical_v2_action" value="migration_resume">';
            echo '<button type="submit" class="button button-secondary">Продолжить Vendor Canonical Migration V2</button>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderExceptions(array $report): void
    {
        $rows = [];

        foreach ((array) ($report['results'] ?? []) as $row) {
            if (
                is_array($row)
                && (string) ($row['status'] ?? '') !== 'UPDATED'
            ) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return;
        }

        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">SKIP / ERROR</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (
            [
                'ID',
                'Status',
                'Old H1',
                'New H1',
                'Slug',
                'Translation',
                'Reason',
            ]
            as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $this->escape((string) ($row['productId'] ?? '')) . '</td>';
            echo '<td><strong>' . $this->escape((string) ($row['status'] ?? '')) . '</strong></td>';
            echo '<td>' . $this->escape((string) ($row['oldTitle'] ?? '')) . '</td>';
            echo '<td>' . $this->escape((string) ($row['newTitle'] ?? '')) . '</td>';
            echo '<td><code>' . $this->escape((string) ($row['slug'] ?? '')) . '</code></td>';
            echo '<td><code>' . $this->escape((string) ($row['translation'] ?? '')) . '</code></td>';
            echo '<td>' . $this->escape((string) ($row['reason'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * @param array<string, int|string> $state
     * @return array{array<string, int|string>, string, string}
     */
    private function processNextBatch(array $state): array
    {
        $ids = array_keys($this->migration->targets());
        $offset = (int) $state['next_offset'];
        $batch = array_slice($ids, $offset, 5);
        $report = $this->loadReport();

        foreach ($batch as $productId) {
            try {
                $result = $this->migration->apply((int) $productId);
            } catch (Throwable $exception) {
                $result = [
                    'productId' => (int) $productId,
                    'status' => 'ERROR',
                    'oldTitle' => '',
                    'newTitle' => '',
                    'slug' => '',
                    'translation' => '',
                    'reason' => $exception->getMessage(),
                ];
            }

            $report['results'][(int) $productId] = $result;
        }

        $state['processed'] = count((array) $report['results']);
        $state['next_offset'] = $offset + count($batch);
        $state['updated_at'] = $this->currentTime();
        $state['error'] = '';

        $finished = $batch === []
            || $state['next_offset'] >= count($ids);

        $state['status'] = $finished ? 'READY' : 'RUNNING';
        $report['updated_at'] = $this->currentTime();
        $this->saveReport($report);
        $this->saveState($state);

        return [
            $state,
            $finished
                ? 'VENDOR CANONICAL NAMING MIGRATION V2 = READY'
                : 'VENDOR CANONICAL NAMING MIGRATION V2 BATCH = SAVED',
            '',
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array{total:int,updated:int,skip:int,error:int}
     */
    private function summary(array $report): array
    {
        $summary = [
            'total' => count($this->migration->targets()),
            'updated' => 0,
            'skip' => 0,
            'error' => 0,
        ];

        foreach ((array) ($report['results'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $status = (string) ($row['status'] ?? '');

            if ($status === 'UPDATED') {
                ++$summary['updated'];
            } elseif ($status === 'SKIP') {
                ++$summary['skip'];
            } elseif ($status === 'ERROR') {
                ++$summary['error'];
            }
        }

        return $summary;
    }

    private function renderAutoContinue(): void
    {
        echo '<form id="wp-shop-vendor-canonical-v2-next" method="post" style="display:none;">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_canonical_v2_action" value="migration_next">';
        echo '</form>';
        echo '<script>';
        echo 'window.setTimeout(function(){var f=document.getElementById("wp-shop-vendor-canonical-v2-next");if(f){f.submit();}},1200);';
        echo '</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(): array
    {
        $empty = [
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
            self::REPORT_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return $empty;
        }

        if (isset($stored['results']) && is_array($stored['results'])) {
            $empty['results'] = $stored['results'];
        }

        foreach (['started_at', 'updated_at'] as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $empty[$key] = $stored[$key];
            }
        }

        return $empty;
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
        $this->saveReport([
            'results' => [],
            'started_at' => $this->currentTime(),
            'updated_at' => $this->currentTime(),
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function newState(int $total): array
    {
        $now = $this->currentTime();

        return [
            'status' => $total === 0 ? 'READY' : 'RUNNING',
            'total' => $total,
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
            'total' => count($this->migration->targets()),
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

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_vendor_canonical_naming_v2',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_vendor_canonical_naming_v2',
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
}
