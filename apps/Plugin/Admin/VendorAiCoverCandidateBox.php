<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use RuntimeException;

final class VendorAiCoverCandidateBox
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function register(object $post): void
    {
        ($this->call)(
            'add_meta_box',
            'wp-shop-vendor-ai-cover',
            'Vendor AI Cover',
            [$this, 'render'],
            'product',
            'side',
            'high'
        );
    }

    public function render(object $post): void
    {
        $productId = (int) ($this->call)(
            'get_post_field',
            'ID',
            $post
        );

        if ($productId <= 0) {
            echo '<p>Vendor AI Cover state unavailable.</p>';

            return;
        }

        $status = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_vendor_cover_status',
            true
        ));
        $candidateId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_candidate_attachment_id',
                true
            )
        );
        $currentId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_current_attachment_id',
                true
            )
        );
        $previousId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_previous_attachment_id',
                true
            )
        );

        echo '<p><strong>Status:</strong> <code>'
            . $this->escape($status !== '' ? $status : 'not_generated')
            . '</code></p>';

        if ($status === 'candidate' && $candidateId > 0) {
            $preview = ($this->call)(
                'wp_get_attachment_image',
                $candidateId,
                'medium',
                false,
                [
                    'style' => 'display:block;width:100%;height:auto;border-radius:6px;margin:8px 0 12px;',
                ]
            );

            if (is_string($preview) && $preview !== '') {
                echo $preview;
            }

            echo '<p><small>Featured Image has not been changed yet.</small></p>';
            $this->actionForm(
                $productId,
                'wp_shop_vendor_cover_approve',
                'Принять AI Cover',
                'button button-primary'
            );
            $this->actionForm(
                $productId,
                'wp_shop_vendor_cover_discard',
                'Отклонить',
                'button'
            );

            return;
        }

        if ($status === 'ready' && $currentId > 0) {
            echo '<p><strong>Approved attachment:</strong> #'
                . $this->escape((string) $currentId)
                . '</p>';

            if ($previousId > 0) {
                $this->actionForm(
                    $productId,
                    'wp_shop_vendor_cover_restore',
                    'Вернуть предыдущую обложку',
                    'button'
                );
            }

            return;
        }

        if ($status === 'discarded') {
            echo '<p>AI candidate was discarded. Current Featured Image was preserved.</p>';

            return;
        }

        if ($status === 'error') {
            $error = trim((string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_error',
                true
            ));
            echo '<p style="color:#b32d2e;">'
                . $this->escape($error !== '' ? $error : 'AI generation failed.')
                . '</p>';

            return;
        }

        echo '<p>No AI cover candidate is waiting for review.</p>';
    }

    public function approve(): void
    {
        $productId = $this->requestProductId();
        $this->verify($productId);

        $candidateId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_candidate_attachment_id',
                true
            )
        );

        if ($candidateId <= 0) {
            throw new RuntimeException(
                'No Vendor AI Cover candidate exists for this product.'
            );
        }

        $set = (bool) ($this->call)(
            'set_post_thumbnail',
            $productId,
            $candidateId
        );

        if (! $set) {
            throw new RuntimeException(
                'WordPress could not set the AI Cover candidate as Featured Image.'
            );
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_generated',
            '1'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_current_attachment_id',
            (string) $candidateId
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_candidate_attachment_id',
            ''
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_status',
            'ready'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_approved_at',
            (string) ($this->call)('current_time', 'mysql')
        );

        $this->redirect($productId, 'approved');
    }

    public function discard(): void
    {
        $productId = $this->requestProductId();
        $this->verify($productId);

        $candidateId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_candidate_attachment_id',
                true
            )
        );

        if ($candidateId > 0) {
            ($this->call)(
                'wp_delete_attachment',
                $candidateId,
                true
            );
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_generated',
            '0'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_candidate_attachment_id',
            ''
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_status',
            'discarded'
        );

        $this->redirect($productId, 'discarded');
    }

    public function restore(): void
    {
        $productId = $this->requestProductId();
        $this->verify($productId);

        $previousId = max(
            0,
            (int) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_previous_attachment_id',
                true
            )
        );

        if ($previousId <= 0) {
            throw new RuntimeException(
                'Previous Featured Image is unavailable.'
            );
        }

        $set = (bool) ($this->call)(
            'set_post_thumbnail',
            $productId,
            $previousId
        );

        if (! $set) {
            throw new RuntimeException(
                'WordPress could not restore the previous Featured Image.'
            );
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_generated',
            '0'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_status',
            'restored'
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_vendor_cover_current_attachment_id',
            ''
        );

        $this->redirect($productId, 'restored');
    }

    private function actionForm(
        int $productId,
        string $action,
        string $label,
        string $class
    ): void {
        $url = (string) ($this->call)(
            'admin_url',
            'admin-post.php'
        );

        echo '<form method="post" action="'
            . $this->escapeUrl($url)
            . '" style="margin:8px 0;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_vendor_cover_candidate_' . $productId,
            '_wpnonce',
            true,
            true
        );
        echo '<input type="hidden" name="action" value="'
            . $this->escapeAttr($action)
            . '">';
        echo '<input type="hidden" name="product_id" value="'
            . $this->escapeAttr((string) $productId)
            . '">';
        echo '<button type="submit" class="'
            . $this->escapeAttr($class)
            . '">'
            . $this->escape($label)
            . '</button>';
        echo '</form>';
    }

    private function requestProductId(): int
    {
        $raw = $_POST['product_id'] ?? 0;

        return max(
            0,
            (int) ($this->call)('wp_unslash', $raw)
        );
    }

    private function verify(int $productId): void
    {
        if (
            $productId <= 0
            || ! (bool) ($this->call)(
                'current_user_can',
                'manage_woocommerce'
            )
        ) {
            ($this->call)(
                'wp_die',
                'You are not allowed to manage this Vendor AI Cover.'
            );
        }

        ($this->call)(
            'check_admin_referer',
            'wp_shop_vendor_cover_candidate_' . $productId,
            '_wpnonce'
        );
    }

    private function redirect(
        int $productId,
        string $notice
    ): void {
        $url = (string) ($this->call)(
            'get_edit_post_link',
            $productId,
            'raw'
        );

        if ($url === '') {
            $url = (string) ($this->call)(
                'admin_url',
                'post.php?post='
                    . $productId
                    . '&action=edit'
            );
        }

        $url .= str_contains($url, '?')
            ? '&wp_shop_cover_notice=' . rawurlencode($notice)
            : '?wp_shop_cover_notice=' . rawurlencode($notice);

        ($this->call)('wp_safe_redirect', $url);
        exit;
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
