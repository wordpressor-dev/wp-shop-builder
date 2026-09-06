<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Admin;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Naming\ElementorProTranslatePressPreflightService;
use WPShop\WordPress\Admin\Contracts\SubmenuPageInterface;

final class ElementorProTranslatePressPreflightPage implements SubmenuPageInterface
{
    private const REPORT_META_KEY =
        'wp_shop_pm_elementor_pro_trp_preflight_v1';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ElementorProTranslatePressPreflightService $preflight,
        private readonly Closure $call
    ) {
    }

    public function parentSlug(): string
    {
        return 'wp-shop-builder';
    }

    public function slug(): string
    {
        return 'wp-shop-builder-elementor-pro-trp-preflight';
    }

    public function title(): string
    {
        return 'Elementor Pro TRP Preflight';
    }

    public function capability(): string
    {
        return 'manage_woocommerce';
    }

    public function render(): void
    {
        $action = $this->posted('wp_shop_pm_elementor_pro_trp_action');
        $message = '';
        $error = '';

        if ($action === 'run') {
            $this->checkNonce();

            try {
                $report = $this->preflight->inspect();
                $this->saveReport($report);
                $message = 'ELEMENTOR PRO TRP PREFLIGHT = READY';
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $report = $this->loadReport();

        echo '<div class="wrap">';
        echo '<h1>WP Shop Product Manager — Elementor Pro TranslatePress Preflight</h1>';
        echo '<p><strong>READ ONLY / PRODUCT WRITES = 0 / TRANSLATION WRITES = 0.</strong> This diagnostic inspects the current and target TranslatePress title rows for product #3585 after the V2 rollback.</p>';

        if ($message !== '') {
            echo '<div class="notice notice-success"><p><strong>'
                . $this->escape($message)
                . '</strong></p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p><strong>PREFLIGHT ERROR:</strong> '
                . $this->escape($error)
                . '</p></div>';
        }

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<form method="post">';
        $this->nonceField();
        echo '<input type="hidden" name="wp_shop_pm_elementor_pro_trp_action" value="run">';
        echo '<button type="submit" class="button button-primary">Run Elementor Pro TranslatePress Preflight — без записи</button>';
        echo '</form>';
        echo '</div>';

        if ($report !== []) {
            $this->renderReport($report);
        }

        echo '</div>';
    }

    /**
     * @param array<string, mixed> $report
     */
    private function renderReport(array $report): void
    {
        echo '<div class="notice notice-info" style="max-width:1500px;padding:10px 14px;">';
        echo '<p><strong>ELEMENTOR PRO TRP PREFLIGHT = READY</strong></p>';
        echo '<p><strong>PRODUCT WRITES = 0</strong> &nbsp; <strong>TRANSLATION WRITES = 0</strong></p>';
        echo '<p>CURRENT STATE = <code>'
            . $this->escape((string) ($report['currentState'] ?? ''))
            . '</code> &nbsp; TARGET INSPECTOR = <code>'
            . $this->escape((string) ($report['targetInspectorState'] ?? ''))
            . '</code> &nbsp; SAFE ACTION = <strong>'
            . $this->escape((string) ($report['safeAction'] ?? ''))
            . '</strong></p>';
        echo '<p>STATUS: TABLE_OK = '
            . $this->escape((string) ((bool) ($report['tableOk'] ?? false) ? 'YES' : 'NO'))
            . ' &nbsp; TOTAL = '
            . $this->escape((string) ($report['total'] ?? 0))
            . ' &nbsp; EXACT = '
            . $this->escape((string) ($report['exact'] ?? 0))
            . ' &nbsp; KEEP = '
            . $this->escape((string) ($report['keep'] ?? 0))
            . ' &nbsp; FILL = '
            . $this->escape((string) ($report['fill'] ?? 0))
            . ' &nbsp; MISSING = '
            . $this->escape((string) ($report['missing'] ?? 0))
            . '</p>';
        echo '</div>';

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">Title snapshot</h2>';
        echo '<table class="widefat striped"><tbody>';
        $pairs = [
            'Product ID' => (string) ($report['productId'] ?? ''),
            'Current H1' => (string) ($report['currentTitle'] ?? ''),
            'Target H1' => (string) ($report['targetTitle'] ?? ''),
            'Current TRP translations' => implode(
                ' | ',
                (array) ($report['currentTranslations'] ?? [])
            ),
            'Target TRP translations' => implode(
                ' | ',
                (array) ($report['targetInspectorTranslations'] ?? [])
            ),
        ];

        foreach ($pairs as $label => $value) {
            echo '<tr><th style="width:260px;">'
                . $this->escape($label)
                . '</th><td>'
                . $this->escape($value)
                . '</td></tr>';
        }

        echo '</tbody></table></div>';

        echo '<div class="postbox" style="max-width:1500px;padding:18px 20px;">';
        echo '<h2 style="margin-top:0;">TranslatePress rows for target: Elementor Pro</h2>';

        $rows = (array) ($report['rows'] ?? []);

        if ($rows === []) {
            echo '<p>No dictionary rows found for the target string.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            foreach (
                [
                    'ID',
                    'Original',
                    'Translated',
                    'Status',
                    'Block type',
                    'Original ID',
                ]
                as $heading
            ) {
                echo '<th>' . $this->escape($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                echo '<tr>';
                echo '<td>' . $this->escape((string) ($row['id'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['original'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['translated'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['status'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['block_type'] ?? '')) . '</td>';
                echo '<td>' . $this->escape((string) ($row['original_id'] ?? '')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadReport(): array
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId <= 0) {
            return [];
        }

        $stored = ($this->call)(
            'get_user_meta',
            $userId,
            self::REPORT_META_KEY,
            true
        );

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function saveReport(array $report): void
    {
        $userId = (int) ($this->call)('get_current_user_id');

        if ($userId > 0) {
            ($this->call)(
                'update_user_meta',
                $userId,
                self::REPORT_META_KEY,
                $report
            );
        }
    }

    private function checkNonce(): void
    {
        ($this->call)(
            'check_admin_referer',
            'wp_shop_pm_elementor_pro_trp_preflight',
            '_wpnonce'
        );
    }

    private function nonceField(): void
    {
        ($this->call)(
            'wp_nonce_field',
            'wp_shop_pm_elementor_pro_trp_preflight',
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
}
