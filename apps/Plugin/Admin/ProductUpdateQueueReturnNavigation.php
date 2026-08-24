<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;

final class ProductUpdateQueueReturnNavigation
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function injectQueueReturnState(): void
    {
        if (! $this->isPage('wp-shop-builder-product-update-queue')) {
            return;
        }

        $state = $this->stateFromPost();
        $json = json_encode(
            $state,
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE
        );

        if (! is_string($json)) {
            return;
        }

        echo '<script>';
        echo '(function(){';
        echo 'const state=' . $json . ';';
        echo 'document.querySelectorAll("form").forEach(function(form){';
        echo 'const actionInput=form.querySelector("input[name=\"wp_shop_pm_update_action\"][value=\"load_product\"]");';
        echo 'const productInput=form.querySelector("input[name=\"update_product_id\"]");';
        echo 'if(!actionInput||!productInput){return;}';
        echo 'const url=new URL(form.action||window.location.href,window.location.href);';
        echo 'url.searchParams.set("wp_shop_pm_return_queue","1");';
        echo 'url.searchParams.set("queue_filter",state.filter);';
        echo 'url.searchParams.set("queue_search",state.search);';
        echo 'url.searchParams.set("queue_per_page",String(state.perPage));';
        echo 'url.searchParams.set("queue_page",String(state.page));';
        echo 'form.action=url.toString();';
        echo '});';
        echo '})();';
        echo '</script>';
    }

    public function renderReturnNotice(): void
    {
        if (! $this->isPage('wp-shop-builder-product-update')) {
            return;
        }

        if ($this->get('wp_shop_pm_return_queue') !== '1') {
            return;
        }

        if (! (bool) ($this->call)('current_user_can', 'manage_woocommerce')) {
            return;
        }

        $state = $this->stateFromGet();
        $queueUrl = (string) ($this->call)(
            'admin_url',
            'admin.php?page=wp-shop-builder-product-update-queue'
        );

        echo '<div class="notice notice-info" style="padding:10px 14px;">';
        echo '<p><strong>RETURN QUEUE = READY</strong> &nbsp; '
            . 'VIEW = ' . $this->escape($this->filterLabel($state['filter']))
            . ' &nbsp; SEARCH = ' . $this->escape(
                $state['search'] !== '' ? $state['search'] : '[empty]'
            )
            . ' &nbsp; PAGE = ' . $this->escape((string) $state['page'])
            . ' &nbsp; PER PAGE = ' . $this->escape((string) $state['perPage'])
            . '</p>';
        echo '<form method="post" action="'
            . $this->escapeUrl($queueUrl)
            . '" style="margin:0 0 4px;">';
        echo '<input type="hidden" name="wp_shop_pm_update_queue_action" value="browse">';
        echo '<input type="hidden" name="queue_filter" value="'
            . $this->escapeAttr($state['filter'])
            . '">';
        echo '<input type="hidden" name="queue_search" value="'
            . $this->escapeAttr($state['search'])
            . '">';
        echo '<input type="hidden" name="queue_per_page" value="'
            . $this->escapeAttr((string) $state['perPage'])
            . '">';
        echo '<input type="hidden" name="queue_page" value="'
            . $this->escapeAttr((string) $state['page'])
            . '">';
        echo '<button type="submit" class="button button-secondary">← Вернуться в Update Queue</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * @return array{filter: string, search: string, perPage: int, page: int}
     */
    private function stateFromPost(): array
    {
        return $this->normalizeState(
            $this->post('queue_filter', 'update_available'),
            $this->post('queue_search'),
            (int) $this->post('queue_per_page', '25'),
            (int) $this->post('queue_page', '1')
        );
    }

    /**
     * @return array{filter: string, search: string, perPage: int, page: int}
     */
    private function stateFromGet(): array
    {
        return $this->normalizeState(
            $this->get('queue_filter', 'update_available'),
            $this->get('queue_search'),
            (int) $this->get('queue_per_page', '25'),
            (int) $this->get('queue_page', '1')
        );
    }

    /**
     * @return array{filter: string, search: string, perPage: int, page: int}
     */
    private function normalizeState(
        string $filter,
        string $search,
        int $perPage,
        int $page
    ): array {
        $filter = in_array(
            $filter,
            ['update_available', 'manual_review', 'all'],
            true
        ) ? $filter : 'update_available';

        $perPage = in_array($perPage, [25, 50, 100], true)
            ? $perPage
            : 25;

        $search = trim(
            (string) ($this->call)(
                'sanitize_text_field',
                $search
            )
        );

        return [
            'filter' => $filter,
            'search' => $search,
            'perPage' => $perPage,
            'page' => max(1, $page),
        ];
    }

    private function filterLabel(string $filter): string
    {
        return match ($filter) {
            'manual_review' => 'MANUAL_REVIEW',
            'all' => 'ALL_ATTENTION',
            default => 'UPDATE_AVAILABLE',
        };
    }

    private function isPage(string $slug): bool
    {
        return $this->get('page') === $slug;
    }

    private function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        if (! is_string($value)) {
            return $default;
        }

        return (string) ($this->call)('wp_unslash', $value);
    }

    private function get(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;

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
