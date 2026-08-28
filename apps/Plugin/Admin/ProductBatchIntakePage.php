<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ProductBatchIntakePage implements SubmenuPageInterface
{
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
        $rows = [];
        $error = '';
        $root = '';
        $folders = [];

        try {
            $root = $this->scanner->ensureInbox($uploadsBaseDir);
            $folders = $this->scanner->folders($uploadsBaseDir);

            if ($this->posted('wp_shop_pm_batch_intake_action') === 'scan') {
                $this->checkNonce();
                $rows = $this->scanner->scan(
                    $uploadsBaseDir,
                    $selectedFolder
                );
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Import Queue</h1>';
        echo '<p>Пакетный вход для ZIP-архивов. На этом этапе сканирование только анализирует файлы и определяет CREATE / UPDATE / REVIEW. Товары и архивы не изменяются.</p>';

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>BATCH INTAKE ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        echo '<div class="notice notice-info" style="max-width:1400px;padding:10px 14px;">';
        echo '<p><strong>INBOX ROOT</strong> = '
            . $this->escape($root !== '' ? $root : 'UNAVAILABLE')
            . '</p>';
        echo '<p>Создай внутри INBOX отдельную папку для партии, загрузи туда ZIP-файлы с исходными именами и выбери эту папку ниже.</p>';
        echo '</div>';

        $this->renderScanForm($folders, $selectedFolder);

        if ($this->posted('wp_shop_pm_batch_intake_action') === 'scan' && $error === '') {
            $this->renderSummary($rows, $selectedFolder);
            $this->renderTable($rows);
        }

        echo '</div>';
    }

    /**
     * @param list<string> $folders
     */
    private function renderScanForm(array $folders, string $selectedFolder): void
    {
        echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">1. Выбрать папку партии</h2>';
        echo '<form method="post" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;">';
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_batch_intake_scan',
            '_wpnonce',
            true,
            true
        );
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

        echo '<div class="notice notice-info" style="max-width:1400px;padding:10px 14px;">';
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
    private function renderTable(array $rows): void
    {
        echo '<div class="postbox" style="max-width:1400px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">2. Проверка партии</h2>';

        if ($rows === []) {
            echo '<p><em>ZIP-файлы в выбранной папке не найдены.</em></p>';
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
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-bottom:0;margin-top:14px;"><strong>Следующий этап v26:</strong> добавить кнопки Apply / Skip / Review и выполнять UPDATE или создавать заполненный Draft прямо из этой очереди.</p>';
        echo '</div>';
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
