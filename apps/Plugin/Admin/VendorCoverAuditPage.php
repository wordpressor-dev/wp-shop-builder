<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverAuditRow;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverAuditService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class VendorCoverAuditPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY = 'wp_shop_pm_vendor_cover_audit_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorCoverAuditService $audit,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-vendor-cover-audit';
    }

    public function title(): string
    {
        return 'Vendor Cover Audit';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_vendor_cover_audit_action');
        $message = '';
        $error = '';

        if ($action === 'run') {
            $this->checkNonce();

            try {
                $rows = $this->audit->scanAll();
                $this->saveReport($rows);
                $message = 'VENDOR COVER AUDIT = READY';
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $rows = $this->loadReport();
        $summary = $this->summary($rows);

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Vendor Cover Audit</h1>';
        echo '<p><strong>READ ONLY / PRODUCT WRITES = 0 / IMAGE WRITES = 0.</strong> Audits Vendor featured images against the unified <strong>590×300 WebP</strong> cover standard and explicitly protects ThemeForest / CodeCanyon / Envato products.</p>';
        echo '<p><strong>Actions:</strong> KEEP / GENERATE / REVIEW / SKIP_MARKETPLACE. Existing Vendor images are never changed by this audit.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>VENDOR COVER AUDIT ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        echo '<div class="postbox" style="max-width:1600px;padding:18px 20px;">';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_cover_audit_action" value="run">';
        echo '<button type="submit" class="button button-primary">Run Vendor Cover Audit — без записи</button>';
        echo '</form>';
        echo '</div>';

        if ($rows !== []) {
            $this->renderSummary($summary);
            $this->renderExport();
            $this->renderRows($rows);
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
            'wp_shop_pm_export_vendor_cover_audit',
            '_wpnonce'
        );

        $rows = $this->loadReport();
        $filename = 'wp-shop-vendor-cover-audit-'
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
                'Current H1',
                'Sales Page',
                'Source',
                'Marketplace Protected',
                'Featured Image ID',
                'Image URL',
                'Image Filename',
                'Width',
                'Height',
                'Format',
                'Image Status',
                'Generated Flag',
                'Cover Lock',
                'Standard Match',
                'Action',
                'Reason',
            ]
        );

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                [
                    (string) ($row['productId'] ?? ''),
                    (string) ($row['currentTitle'] ?? ''),
                    (string) ($row['salesPage'] ?? ''),
                    (string) ($row['sourceType'] ?? ''),
                    $this->yesNo((bool) ($row['marketplaceProtected'] ?? false)),
                    (string) ($row['featuredImageId'] ?? ''),
                    (string) ($row['imageUrl'] ?? ''),
                    (string) ($row['imageFilename'] ?? ''),
                    (string) ($row['imageWidth'] ?? ''),
                    (string) ($row['imageHeight'] ?? ''),
                    (string) ($row['imageFormat'] ?? ''),
                    (string) ($row['imageStatus'] ?? ''),
                    $this->yesNo((bool) ($row['generated'] ?? false)),
                    $this->yesNo((bool) ($row['locked'] ?? false)),
                    $this->yesNo((bool) ($row['standardMatch'] ?? false)),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                ]
            );
        }

        fclose($stream);
        exit;
    }

    /**
     * @param array<string, int> $summary
     */
    private function renderSummary(array $summary): void
    {
        echo '<div class="notice notice-info" style="max-width:1600px;padding:10px 14px;">';
        echo '<p><strong>VENDOR COVER AUDIT = READY</strong></p>';
        echo '<p><strong>PRODUCT WRITES = 0</strong> &nbsp; <strong>IMAGE WRITES = 0</strong></p>';
        echo '<p>TOTAL = ' . $this->escape((string) $summary['total'])
            . ' &nbsp; KEEP = ' . $this->escape((string) $summary['keep'])
            . ' &nbsp; GENERATE = ' . $this->escape((string) $summary['generate'])
            . ' &nbsp; REVIEW = ' . $this->escape((string) $summary['review'])
            . ' &nbsp; SKIP_MARKETPLACE = ' . $this->escape((string) $summary['skipMarketplace'])
            . '</p>';
        echo '</div>';
    }

    /**
     * @param list<array<string, bool|int|string>> $rows
     */
    private function renderRows(array $rows): void
    {
        echo '<div class="postbox" style="max-width:2000px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Vendor Cover Audit Results</h2>';
        echo '<table class="widefat striped"><thead><tr>';

        foreach (
            [
                'ID',
                'Current H1',
                'Source',
                'Marketplace',
                'Image',
                'Size',
                'Format',
                'Status',
                'Generated',
                'Locked',
                'Standard',
                'Action',
                'Reason',
                'Product',
            ]
            as $heading
        ) {
            echo '<th>' . $this->escape($heading) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $productId = (int) ($row['productId'] ?? 0);
            $width = (int) ($row['imageWidth'] ?? 0);
            $height = (int) ($row['imageHeight'] ?? 0);
            $size = $width > 0 && $height > 0
                ? $width . '×' . $height
                : '—';

            echo '<tr>';
            echo '<td>' . $this->escape((string) $productId) . '</td>';
            echo '<td>' . $this->escape((string) ($row['currentTitle'] ?? '')) . '</td>';
            echo '<td><code>' . $this->escape((string) ($row['sourceType'] ?? '')) . '</code></td>';
            echo '<td>' . $this->escape($this->yesNo((bool) ($row['marketplaceProtected'] ?? false))) . '</td>';
            echo '<td>' . $this->escape((string) ($row['imageFilename'] ?? '')) . '</td>';
            echo '<td>' . $this->escape($size) . '</td>';
            echo '<td>' . $this->escape((string) ($row['imageFormat'] ?? '')) . '</td>';
            echo '<td><code>' . $this->escape((string) ($row['imageStatus'] ?? '')) . '</code></td>';
            echo '<td>' . $this->escape($this->yesNo((bool) ($row['generated'] ?? false))) . '</td>';
            echo '<td>' . $this->escape($this->yesNo((bool) ($row['locked'] ?? false))) . '</td>';
            echo '<td>' . $this->escape($this->yesNo((bool) ($row['standardMatch'] ?? false))) . '</td>';
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

        echo '</tbody></table></div>';
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
            'wp_shop_pm_export_vendor_cover_audit',
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="wp_shop_pm_export_vendor_cover_audit">';
        echo '<button type="submit" class="button button-secondary">Export Vendor Cover Audit CSV</button>';
        echo '</form>';
    }

    /**
     * @param list<VendorCoverAuditRow> $rows
     */
    private function saveReport(array $rows): void
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return;
        }

        $stored = [];

        foreach ($rows as $row) {
            $stored[] = $row->toArray();
        }

        ($this->call)(
            'update_user_meta',
            $userId,
            self::REPORT_META_KEY,
            $stored
        );
    }

    /**
     * @return list<array<string, bool|int|string>>
     */
    private function loadReport(): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::REPORT_META_KEY,
            true
        );

        if (! is_array($stored)) {
            return [];
        }

        $rows = [];

        foreach ($stored as $row) {
            if (is_array($row)) {
                /** @var array<string, bool|int|string> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, bool|int|string>> $rows
     * @return array{total:int,keep:int,generate:int,review:int,skipMarketplace:int}
     */
    private function summary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'keep' => 0,
            'generate' => 0,
            'review' => 0,
            'skipMarketplace' => 0,
        ];

        foreach ($rows as $row) {
            $action = (string) ($row['action'] ?? '');

            if ($action === 'KEEP') {
                ++$summary['keep'];
            } elseif ($action === 'GENERATE') {
                ++$summary['generate'];
            } elseif ($action === 'REVIEW') {
                ++$summary['review'];
            } elseif ($action === 'SKIP_MARKETPLACE') {
                ++$summary['skipMarketplace'];
            }
        }

        return $summary;
    }

    private function currentUserId(): int
    {
        return (int) ($this->call)('get_current_user_id');
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_vendor_cover_audit',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_vendor_cover_audit',
            '_wpnonce',
            true,
            true
        );
    }

    private function posted(string $key): string
    {
        $value = $_POST[$key] ?? '';

        if (! is_string($value)) {
            return '';
        }

        return (string) ($this->call)('wp_unslash', $value);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'YES' : 'NO';
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
