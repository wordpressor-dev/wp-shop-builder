<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverPreviewService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class VendorCoverGeneratorPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY =
        'wp_shop_pm_vendor_cover_generator_preview_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorCoverPreviewService $preview,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-vendor-cover-generator';
    }

    public function title(): string
    {
        return 'Vendor Cover Generator';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted(
            'wp_shop_pm_vendor_cover_generator_action'
        );
        $message = '';
        $error = '';

        if ($action === 'preview') {
            $this->checkNonce();

            try {
                $ids = $this->parseIds(
                    $this->posted('product_ids')
                );
                $rows = $this->preview->generate($ids);
                $this->saveReport($rows);
                $message = 'VENDOR COVER PREVIEW = READY';
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $caps = $this->preview->capabilities();
        $rows = $this->loadReport();

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Vendor Cover Generator</h1>';
        echo '<p><strong>TEST MODE / PRODUCT WRITES = 0 / FEATURED IMAGE WRITES = 0.</strong> This page creates only preview WebP files in uploads. It does not create Media Library attachments and does not replace any product image.</p>';
        echo '<p><strong>Approved visual direction:</strong> one unified WP Shop color system, 590×300 WebP, canonical H1, short English functional subtitle, premium product card on the right.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>VENDOR COVER PREVIEW ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        $this->renderCapabilities($caps);
        $this->renderControls();

        if ($rows !== []) {
            $this->renderRows($rows);
        }

        echo '</div>';
    }

    /**
     * @param array{gd:bool,webp:bool,ttf:bool,boldFont:string,regularFont:string} $caps
     */
    private function renderCapabilities(array $caps): void
    {
        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>RENDER ENGINE</strong> &nbsp; GD = '
            . $this->escape($this->yesNo($caps['gd']))
            . ' &nbsp; WEBP = '
            . $this->escape($this->yesNo($caps['webp']))
            . ' &nbsp; TTF = '
            . $this->escape($this->yesNo($caps['ttf']))
            . '</p>';

        if (! $caps['ttf']) {
            echo '<p><strong>Warning:</strong> no supported server TrueType font was found. Preview can still render with a basic fallback font, but typography quality will be lower.</p>';
        }

        echo '</div>';
    }

    private function renderControls(): void
    {
        $defaults = implode(
            ',',
            VendorCoverPreviewService::DEFAULT_PRODUCT_IDS
        );

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Generate test covers</h2>';
        echo '<p>Default test set: Elementor Pro, Yoast SEO Premium, WPML Multilingual CMS, JetWooBuilder, FiboSearch Pro.</p>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_vendor_cover_generator_action" value="preview">';
        echo '<p><label><strong>Vendor Product IDs</strong><br>';
        echo '<input type="text" name="product_ids" value="'
            . $this->escapeAttr($defaults)
            . '" style="width:520px;" placeholder="3585,2681,2995,3496,4409">';
        echo '</label></p>';
        echo '<p><small>Maximum 5 products per preview run.</small></p>';
        echo '<button type="submit" class="button button-primary">Generate 5 Test Covers — preview only</button>';
        echo '</form></div>';
    }

    /**
     * @param list<array<string, bool|int|string>> $rows
     */
    private function renderRows(array $rows): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Preview Covers</h2>';
        echo '<p><strong>PRODUCT WRITES = 0 / FEATURED IMAGE WRITES = 0.</strong> Review the five covers below before enabling any catalog migration.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(590px,1fr));gap:22px;">';

        foreach ($rows as $row) {
            $productId = (int) ($row['productId'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            echo '<div style="border:1px solid #dcdcde;border-radius:8px;padding:14px;background:#fff;">';
            echo '<p style="margin-top:0;"><strong>#'
                . $this->escape((string) $productId)
                . ' — '
                . $this->escape((string) ($row['title'] ?? ''))
                . '</strong> &nbsp; <code>'
                . $this->escape($status)
                . '</code></p>';

            if ($status === 'READY') {
                echo '<img src="'
                    . $this->escapeUrl((string) ($row['url'] ?? ''))
                    . '" width="590" height="300" alt="" style="display:block;max-width:100%;height:auto;border-radius:6px;">';
                echo '<p><strong>Subtitle:</strong> '
                    . $this->escape((string) ($row['subtitle'] ?? ''))
                    . '</p>';
                echo '<p><small>'
                    . $this->escape((string) ($row['filename'] ?? ''))
                    . '</small></p>';
            } else {
                echo '<p>'
                    . $this->escape((string) ($row['reason'] ?? ''))
                    . '</p>';
            }

            echo '</div>';
        }

        echo '</div></div>';
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $value): array
    {
        $raw = preg_split('/[\s,;]+/', trim($value)) ?: [];
        $ids = [];

        foreach ($raw as $item) {
            $productId = (int) $item;

            if ($productId <= 0 || in_array($productId, $ids, true)) {
                continue;
            }

            $ids[] = $productId;

            if (count($ids) >= 5) {
                break;
            }
        }

        if ($ids === []) {
            return VendorCoverPreviewService::DEFAULT_PRODUCT_IDS;
        }

        return $ids;
    }

    /**
     * @param list<array<string, bool|int|string>> $rows
     */
    private function saveReport(array $rows): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::REPORT_META_KEY,
                $rows
            );
        }
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

    private function currentUserId(): int
    {
        return (int) ($this->call)('get_current_user_id');
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_vendor_cover_generator',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_vendor_cover_generator',
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

    private function escapeAttr(string $value): string
    {
        return (string) ($this->call)('esc_attr', $value);
    }

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
