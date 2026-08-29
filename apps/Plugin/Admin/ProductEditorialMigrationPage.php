<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductEditorialMigrationPage implements SubmenuPageInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductEditorialMigrationService $migration,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-product-editorial-migration';
    }

    public function title(): string
    {
        return 'Editorial Migration';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $search = trim($this->request('editorial_search'));
        $page = max(1, (int) $this->request('editorial_page', '1'));
        $perPage = $this->perPage(
            (int) $this->request('editorial_per_page', '25')
        );
        $previewId = max(0, (int) $this->request('preview_id'));
        $logs = [];
        $success = null;

        try {
            $applyId = $this->postedInt('editorial_apply_id');
            $restoreId = $this->postedInt('editorial_restore_id');
            $action = $this->posted('wp_shop_pm_editorial_action');

            if ($applyId > 0) {
                $this->checkNonce();
                $logs = $this->migration->apply($applyId);
                $success = true;
                $previewId = $applyId;
            } elseif ($restoreId > 0) {
                $this->checkNonce();
                $logs = $this->migration->restore($restoreId);
                $success = true;
                $previewId = $restoreId;
            } elseif ($action === 'apply_selected') {
                $this->checkNonce();
                $selected = $this->selectedIds();

                if ($selected === []) {
                    throw new \RuntimeException('No products selected.');
                }

                if (count($selected) > 25) {
                    throw new \RuntimeException(
                        'Apply selected is limited to 25 products per run.'
                    );
                }

                foreach ($selected as $productId) {
                    $logs = array_merge(
                        $logs,
                        ['--- PRODUCT ' . $productId . ' ---'],
                        $this->migration->apply($productId)
                    );
                }

                $success = true;
            }
        } catch (Throwable $exception) {
            $success = false;
            $logs = [
                'EDITORIAL MIGRATION = STOPPED',
                'ERROR TYPE = ' . $exception::class,
                'ERROR MESSAGE = ' . $exception->getMessage(),
                'NO FURTHER PRODUCTS WRITTEN = YES',
            ];
        }

        $ids = $this->productIds($search, $page, $perPage + 1);
        $hasNext = count($ids) > $perPage;
        $ids = array_slice($ids, 0, $perPage);
        $rows = [];

        foreach ($ids as $productId) {
            try {
                $rows[] = $this->migration->preview($productId);
            } catch (Throwable) {
                // Skip invalid/non-product rows defensively.
            }
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Editorial Migration</h1>';
        echo '<p>Приведение старых товаров к единому v28 editorial-стандарту. Сначала Preview, затем Apply. Перед первой заменой каждого товара автоматически сохраняется исходный RU/EN/Meta backup.</p>';

        $this->renderLogs($logs, $success);

        echo '<div class="notice notice-warning" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>SAFE MODE:</strong> ZIP, SKU, Download URL, изображения, категории, теги, атрибуты и Advanced Labels не изменяются. На этом этапе TranslatePress dictionary также не переписывается; подготавливаются новые EN Short/Long/Meta для отдельной проверки перевода.</p>';
        echo '</div>';

        $this->renderBrowseForm($search, $perPage);
        $this->renderTable($rows, $search, $page, $perPage, $hasNext);

        if ($previewId > 0) {
            try {
                $this->renderPreview(
                    $this->migration->preview($previewId),
                    $search,
                    $page,
                    $perPage
                );
            } catch (Throwable $exception) {
                echo '<div class="notice notice-error"><p>'
                    . $this->escape($exception->getMessage())
                    . '</p></div>';
            }
        }

        echo '</div>';
    }

    private function renderBrowseForm(string $search, int $perPage): void
    {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<form method="get" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="'
            . $this->escapeAttr($this->slug())
            . '">';
        echo '<input type="hidden" name="editorial_page" value="1">';
        echo '<label><strong>Поиск товара / ID</strong><br>';
        echo '<input type="search" name="editorial_search" value="'
            . $this->escapeAttr($search)
            . '" placeholder="Edubin или 4561" style="width:320px;max-width:100%;"></label>';
        echo '<label><strong>На странице</strong><br><select name="editorial_per_page">';

        foreach ([10, 25, 50] as $value) {
            echo '<option value="' . $value . '"'
                . ($perPage === $value ? ' selected' : '')
                . '>' . $value . '</option>';
        }

        echo '</select></label>';
        echo '<button class="button button-primary" type="submit">Показать товары</button>';
        echo '</form></div>';
    }

    /**
     * @param list<array{
     *   productId:int,title:string,baseTitle:string,status:string,productType:string,
     *   developer:string,sourceUpdateDate:string,ruStatus:string,enStatus:string,
     *   metaStatus:string,backupAvailable:bool,
     *   current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     *   generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * }> $rows
     */
    private function renderTable(
        array $rows,
        string $search,
        int $page,
        int $perPage,
        bool $hasNext
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:16px 18px;">';
        echo '<h2 style="margin-top:0;">Catalog Editorial Queue</h2>';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_editorial_action" value="apply_selected">';
        echo '<input type="hidden" name="editorial_search" value="'
            . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="'
            . $this->escapeAttr((string) $page) . '">';
        echo '<input type="hidden" name="editorial_per_page" value="'
            . $this->escapeAttr((string) $perPage) . '">';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th style="width:34px;"></th><th>ID</th><th>Product</th><th>Type</th><th>RU</th><th>EN</th><th>Meta</th><th>Overall</th><th>Backup</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="10"><em>Товары не найдены.</em></td></tr>';
        }

        foreach ($rows as $row) {
            $id = $row['productId'];
            $previewUrl = $this->pageUrl([
                'preview_id' => (string) $id,
                'editorial_search' => $search,
                'editorial_page' => (string) $page,
                'editorial_per_page' => (string) $perPage,
            ]);
            $editUrl = (string) ($this->call)(
                'get_edit_post_link',
                $id,
                ''
            );

            echo '<tr>';
            echo '<td><input type="checkbox" name="editorial_selected[]" value="'
                . $this->escapeAttr((string) $id) . '"></td>';
            echo '<td>' . $this->escape((string) $id) . '</td>';
            echo '<td><strong>' . $this->escape($row['title']) . '</strong>';

            if ($editUrl !== '') {
                echo '<br><a href="' . $this->escapeUrl($editUrl) . '">Edit product</a>';
            }

            echo '</td>';
            echo '<td>' . $this->escape($row['productType']) . '</td>';
            echo '<td>' . $this->statusBadge($row['ruStatus']) . '</td>';
            echo '<td>' . $this->statusBadge($row['enStatus']) . '</td>';
            echo '<td>' . $this->statusBadge($row['metaStatus']) . '</td>';
            echo '<td><strong>' . $this->escape($row['status']) . '</strong></td>';
            echo '<td>' . ($row['backupAvailable'] ? 'YES' : '—') . '</td>';
            echo '<td style="white-space:nowrap;">';
            echo '<a class="button button-small" href="'
                . $this->escapeUrl($previewUrl) . '">Preview</a> ';
            echo '<button class="button button-small button-primary" type="submit" name="editorial_apply_id" value="'
                . $this->escapeAttr((string) $id)
                . '" onclick="return confirm(\'Replace RU/EN/Meta for product #'
                . $this->escapeAttr((string) $id)
                . '? Backup will be created first.\');">Apply</button> ';

            if ($row['backupAvailable']) {
                echo '<button class="button button-small" type="submit" name="editorial_restore_id" value="'
                    . $this->escapeAttr((string) $id)
                    . '" onclick="return confirm(\'Restore editorial backup for product #'
                    . $this->escapeAttr((string) $id)
                    . '?\');">Restore</button>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-bottom:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Apply v28 editorial content to all selected products? Each product is backed up first.\');">Apply selected (max 25)</button>';
        echo '<span>Выбирай только проверенные строки. CURRENT будет безопасно пропущен без записи.</span></p>';
        echo '</form>';
        $this->renderPagination($search, $page, $perPage, $hasNext);
        echo '</div>';
    }

    /**
     * @param array{
     *   productId:int,title:string,baseTitle:string,status:string,productType:string,
     *   developer:string,sourceUpdateDate:string,ruStatus:string,enStatus:string,
     *   metaStatus:string,backupAvailable:bool,
     *   current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     *   generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * } $preview
     */
    private function renderPreview(
        array $preview,
        string $search,
        int $page,
        int $perPage
    ): void {
        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Preview — #'
            . $this->escape((string) $preview['productId'])
            . ' ' . $this->escape($preview['title']) . '</h2>';
        echo '<p><strong>Overall:</strong> ' . $this->escape($preview['status'])
            . ' &nbsp; <strong>Type:</strong> ' . $this->escape($preview['productType'])
            . ' &nbsp; <strong>Developer:</strong> ' . $this->escape($preview['developer'] !== '' ? $preview['developer'] : '—')
            . ' &nbsp; <strong>Source date:</strong> ' . $this->escape($preview['sourceUpdateDate'] !== '' ? $preview['sourceUpdateDate'] : '—')
            . '</p>';
        echo '<table class="widefat striped"><thead><tr><th style="width:160px;">Field</th><th>Current</th><th>Generated v28</th></tr></thead><tbody>';

        foreach (
            [
                'ruShort' => 'RU Short',
                'ruLong' => 'RU Long',
                'ruMeta' => 'RU SureRank Meta',
                'enShort' => 'EN Short',
                'enLong' => 'EN Long',
                'enMeta' => 'EN Meta',
            ] as $key => $label
        ) {
            $height = str_contains($key, 'Long') ? '180px' : '90px';
            echo '<tr><th>' . $this->escape($label) . '</th><td>';
            echo '<textarea readonly style="width:100%;height:' . $height . ';font-family:monospace;">'
                . $this->escape($preview['current'][$key])
                . '</textarea></td><td>';
            echo '<textarea readonly style="width:100%;height:' . $height . ';font-family:monospace;">'
                . $this->escape($preview['generated'][$key])
                . '</textarea></td></tr>';
        }

        echo '</tbody></table>';
        echo '<form method="post" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">';
        $this->nonceField();
        echo '<input type="hidden" name="editorial_search" value="'
            . $this->escapeAttr($search) . '">';
        echo '<input type="hidden" name="editorial_page" value="'
            . $this->escapeAttr((string) $page) . '">';
        echo '<input type="hidden" name="editorial_per_page" value="'
            . $this->escapeAttr((string) $perPage) . '">';
        echo '<input type="hidden" name="preview_id" value="'
            . $this->escapeAttr((string) $preview['productId']) . '">';
        echo '<button class="button button-primary" type="submit" name="editorial_apply_id" value="'
            . $this->escapeAttr((string) $preview['productId'])
            . '" onclick="return confirm(\'Apply generated editorial content to this product?\');">Apply this product</button>';

        if ($preview['backupAvailable']) {
            echo '<button class="button" type="submit" name="editorial_restore_id" value="'
                . $this->escapeAttr((string) $preview['productId'])
                . '" onclick="return confirm(\'Restore saved editorial backup?\');">Restore backup</button>';
        }

        echo '</form></div>';
    }

    private function renderPagination(
        string $search,
        int $page,
        int $perPage,
        bool $hasNext
    ): void {
        echo '<p style="display:flex;gap:8px;align-items:center;">';

        if ($page > 1) {
            echo '<a class="button" href="'
                . $this->escapeUrl($this->pageUrl([
                    'editorial_search' => $search,
                    'editorial_page' => (string) ($page - 1),
                    'editorial_per_page' => (string) $perPage,
                ])) . '">← Previous</a>';
        }

        echo '<strong>Page ' . $this->escape((string) $page) . '</strong>';

        if ($hasNext) {
            echo '<a class="button" href="'
                . $this->escapeUrl($this->pageUrl([
                    'editorial_search' => $search,
                    'editorial_page' => (string) ($page + 1),
                    'editorial_per_page' => (string) $perPage,
                ])) . '">Next →</a>';
        }

        echo '</p>';
    }

    /** @return list<int> */
    private function productIds(string $search, int $page, int $limit): array
    {
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => $limit,
            'offset' => ($page - 1) * ($limit - 1),
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ];

        if ($search !== '') {
            if (ctype_digit($search)) {
                $args['post__in'] = [(int) $search];
                $args['orderby'] = 'post__in';
            } else {
                $args['s'] = $search;
            }
        }

        $ids = ($this->call)('get_posts', $args);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }

    /** @return list<int> */
    private function selectedIds(): array
    {
        $raw = $_POST['editorial_selected'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            if (is_scalar($value)) {
                $id = (int) $value;

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function perPage(int $value): int
    {
        return in_array($value, [10, 25, 50], true) ? $value : 25;
    }

    private function statusBadge(string $status): string
    {
        $style = match ($status) {
            'CURRENT' => 'color:#008a20;font-weight:700;',
            'MISSING' => 'color:#b32d2e;font-weight:700;',
            default => 'color:#996800;font-weight:700;',
        };

        return '<span style="' . $style . '">' . $this->escape($status) . '</span>';
    }

    /** @param list<string> $logs */
    private function renderLogs(array $logs, ?bool $success): void
    {
        if ($logs === []) {
            return;
        }

        $class = $success === true ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . '"><pre style="white-space:pre-wrap;">'
            . $this->escape(implode("\n", $logs))
            . '</pre></div>';
    }

    /** @param array<string,string> $args */
    private function pageUrl(array $args): string
    {
        $query = array_merge(['page' => $this->slug()], $args);

        return (string) ($this->call)(
            'admin_url',
            'admin.php?' . http_build_query($query)
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_editorial_migration',
            '_wpnonce',
            true,
            true
        );
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_editorial_migration'
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

    private function postedInt(string $key): int
    {
        return (int) $this->posted($key);
    }

    private function request(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        if (! is_scalar($value)) {
            return $default;
        }

        return trim((string) ($this->call)(
            'sanitize_text_field',
            (string) $value
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

    private function escapeUrl(string $value): string
    {
        return (string) ($this->call)('esc_url', $value);
    }
}
