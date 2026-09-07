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

        $this->renderBrowserEngine();
        $this->renderControls();

        if ($rows !== []) {
            $this->renderRows($rows);
        }

        echo '</div>';
    }

    private function renderBrowserEngine(): void
    {
        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>RENDER ENGINE = BROWSER_CANVAS</strong> &nbsp; ';
        echo '<span id="wp-shop-cover-webp-status">WEBP = CHECKING…</span></p>';
        echo '<p>Typography is rendered by the browser, not by server GD/Imagick fonts. This avoids the missing-glyph problem seen in the previous preview.</p>';
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
        echo '<p><strong>PRODUCT WRITES = 0 / FEATURED IMAGE WRITES = 0 / SERVER IMAGE WRITES = 0.</strong> The previews below are drawn locally in the browser.</p>';
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
                echo '<canvas class="wp-shop-vendor-cover-canvas" width="590" height="300" data-title="'
                    . $this->escapeAttr((string) ($row['title'] ?? ''))
                    . '" data-subtitle="'
                    . $this->escapeAttr((string) ($row['subtitle'] ?? ''))
                    . '" data-product-type="'
                    . $this->escapeAttr((string) ($row['productType'] ?? ''))
                    . '" style="display:block;max-width:100%;height:auto;border-radius:6px;"></canvas>';
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

        $this->renderCanvasScript();
    }

    private function renderCanvasScript(): void
    {
        echo <<<'HTML'
<script>
(function () {
    "use strict";

    function roundedRect(ctx, x, y, w, h, r, fill, stroke) {
        var radius = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();

        if (fill) {
            ctx.fillStyle = fill;
            ctx.fill();
        }

        if (stroke) {
            ctx.strokeStyle = stroke;
            ctx.stroke();
        }
    }

    function words(text) {
        return String(text || "").trim().split(/\s+/).filter(Boolean);
    }

    function wrap(ctx, text, maxWidth, maxLines) {
        var list = words(text);
        var lines = [];
        var line = "";

        list.forEach(function (word) {
            var test = line ? line + " " + word : word;

            if (
                line
                && ctx.measureText(test).width > maxWidth
                && lines.length < maxLines - 1
            ) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        });

        if (line && lines.length < maxLines) {
            lines.push(line);
        }

        if (lines.length === maxLines) {
            var all = lines.join(" ");
            var used = words(all).length;

            if (used < list.length) {
                var last = lines.length - 1;

                while (
                    lines[last]
                    && ctx.measureText(lines[last] + "…").width > maxWidth
                ) {
                    lines[last] = lines[last].slice(0, -1);
                }

                lines[last] = lines[last].replace(/[\s,.;:-]+$/, "") + "…";
            }
        }

        return lines;
    }

    function titleLayout(ctx, title) {
        var size = 35;
        var lines = [];

        while (size >= 24) {
            ctx.font = "700 " + size
                + "px Segoe UI, Arial, Helvetica, sans-serif";
            lines = wrap(ctx, title, 300, 2);

            if (
                lines.length <= 2
                && lines.every(function (line) {
                    return ctx.measureText(line).width <= 300;
                })
            ) {
                return {size: size, lines: lines};
            }

            size -= 1;
        }

        ctx.font = "700 24px Segoe UI, Arial, Helvetica, sans-serif";

        return {
            size: 24,
            lines: wrap(ctx, title, 300, 2)
        };
    }

    function monogram(title) {
        var list = words(
            String(title || "").replace(/[^A-Za-z0-9 ]+/g, " ")
        );

        if (list.length >= 2) {
            return (
                list[0].charAt(0)
                + list[1].charAt(0)
            ).toUpperCase();
        }

        return String(title || "WP").slice(0, 2).toUpperCase();
    }

    function featureRow(ctx, x, y, label) {
        ctx.fillStyle = "#31d0aa";
        ctx.beginPath();
        ctx.arc(x + 6, y - 4, 6, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = "#ffffff";
        ctx.lineWidth = 1.6;
        ctx.beginPath();
        ctx.moveTo(x + 3, y - 4);
        ctx.lineTo(x + 5.5, y - 1.5);
        ctx.lineTo(x + 10, y - 7);
        ctx.stroke();

        ctx.fillStyle = "#17223b";
        ctx.font = "500 10.5px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText(label, x + 20, y);
    }

    function drawCover(canvas) {
        var ctx = canvas.getContext("2d");
        var title = canvas.dataset.title || "Premium WordPress Product";
        var subtitle = canvas.dataset.subtitle
            || "Premium WordPress plugin for your website";
        var productType = String(canvas.dataset.productType || "plugin")
            .toLowerCase();
        var typeLabel = productType === "theme"
            ? "WordPress theme"
            : "WordPress plugin";
        var pill = productType === "theme"
            ? "PREMIUM WORDPRESS THEME"
            : "PREMIUM WORDPRESS PLUGIN";

        ctx.clearRect(0, 0, 590, 300);

        var background = ctx.createLinearGradient(0, 0, 590, 300);
        background.addColorStop(0, "#0d1830");
        background.addColorStop(0.55, "#172452");
        background.addColorStop(1, "#402479");
        ctx.fillStyle = background;
        ctx.fillRect(0, 0, 590, 300);

        ctx.fillStyle = "rgba(49,208,170,.16)";
        ctx.beginPath();
        ctx.arc(545, 45, 110, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = "rgba(124,58,237,.18)";
        ctx.beginPath();
        ctx.arc(410, 290, 145, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = "rgba(255,255,255,.04)";
        ctx.beginPath();
        ctx.moveTo(0, 25);
        ctx.bezierCurveTo(110, 52, 198, 12, 315, 36);
        ctx.bezierCurveTo(424, 58, 508, 10, 590, 22);
        ctx.lineTo(590, 0);
        ctx.lineTo(0, 0);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = "#31d0aa";
        ctx.beginPath();
        ctx.arc(35, 30, 13, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = "#ffffff";
        ctx.textAlign = "center";
        ctx.font = "700 10px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText("WP", 35, 34);

        ctx.textAlign = "left";
        ctx.font = "700 13px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText("WP SHOP", 56, 35);

        var layout = titleLayout(ctx, title);
        ctx.fillStyle = "#ffffff";
        ctx.font = "700 " + layout.size
            + "px Segoe UI, Arial, Helvetica, sans-serif";
        var titleY = 98;

        layout.lines.forEach(function (line) {
            ctx.fillText(line, 32, titleY);
            titleY += layout.size + 8;
        });

        ctx.fillStyle = "#d7deef";
        ctx.font = "400 15px Segoe UI, Arial, Helvetica, sans-serif";
        var subtitleLines = wrap(ctx, subtitle, 300, 2);
        var subtitleY = Math.max(180, titleY + 5);

        subtitleLines.forEach(function (line) {
            ctx.fillText(line, 32, subtitleY);
            subtitleY += 21;
        });

        roundedRect(
            ctx,
            32,
            247,
            212,
            31,
            15,
            "rgba(49,208,170,.18)",
            "rgba(49,208,170,.48)"
        );
        ctx.fillStyle = "#5ee6c5";
        ctx.font = "700 10.5px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText(pill, 47, 267);

        ctx.save();
        ctx.shadowColor = "rgba(0,0,0,.24)";
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 8;
        roundedRect(ctx, 356, 43, 208, 218, 18, "#fbfcff");
        ctx.restore();

        var accent = ctx.createLinearGradient(374, 62, 432, 120);
        accent.addColorStop(0, "#31d0aa");
        accent.addColorStop(1, "#7c3aed");
        roundedRect(ctx, 374, 62, 58, 58, 14, accent);

        ctx.fillStyle = "#ffffff";
        ctx.textAlign = "center";
        ctx.font = "700 23px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText(monogram(title), 403, 99);

        ctx.textAlign = "left";
        ctx.fillStyle = "#7b849b";
        ctx.font = "400 10px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText(typeLabel, 448, 78);

        ctx.fillStyle = "#17223b";
        ctx.font = "700 14px Segoe UI, Arial, Helvetica, sans-serif";
        ctx.fillText("Premium", 448, 101);

        ctx.strokeStyle = "#e1e6f0";
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(374, 137);
        ctx.lineTo(546, 137);
        ctx.stroke();

        featureRow(ctx, 374, 164, "Clean product package");
        featureRow(ctx, 374, 195, "Unified WP Shop cover");
        featureRow(ctx, 374, 226, "Ready for catalog");

        roundedRect(ctx, 374, 241, 172, 10, 5, "#e7ebf4");
        roundedRect(ctx, 374, 241, 102, 10, 5, "#31d0aa");
    }

    var test = document.createElement("canvas");
    var webp = test.toDataURL("image/webp").indexOf("data:image/webp") === 0;
    var status = document.getElementById("wp-shop-cover-webp-status");

    if (status) {
        status.textContent = "WEBP = " + (webp ? "YES" : "NO");
    }

    document.querySelectorAll(".wp-shop-vendor-cover-canvas")
        .forEach(drawCover);
}());
</script>
HTML;
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

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }

    private function escapeAttr(string $value): string
    {
        return (string) ($this->call)('esc_attr', $value);
    }

}
