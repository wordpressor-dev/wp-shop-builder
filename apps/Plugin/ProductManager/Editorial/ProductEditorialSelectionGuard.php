<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use Closure;
use Throwable;

final class ProductEditorialSelectionGuard
{
    private const PAGE_SLUG = 'wp-shop-builder-product-editorial-migration';

    /** @var list<string> */
    private array $notices = [];

    /** @param Closure(string, mixed...): mixed $call */
    public function __construct(
        private readonly ProductEditorialMigrationService $migration,
        private readonly Closure $call
    ) {
    }

    public function register(): void
    {
        ($this->call)('add_action', 'admin_init', [$this, 'filterPreparePackSelection'], 1);
        ($this->call)('add_action', 'admin_notices', [$this, 'renderNotices']);
    }

    public function filterPreparePackSelection(): void
    {
        if ($this->request('page') !== self::PAGE_SLUG) {
            return;
        }

        if ($this->request('wp_shop_pm_editorial_action') !== 'prepare_en_pack') {
            return;
        }

        if (! (bool) ($this->call)('current_user_can', 'manage_woocommerce')) {
            return;
        }

        $selected = $this->selectedIds();
        if ($selected === []) {
            return;
        }

        $kept = [];
        $skipped = [];

        foreach ($selected as $id) {
            try {
                $preview = $this->migration->preview($id);
            } catch (Throwable $exception) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / PREVIEW ERROR: ' . $exception->getMessage();
                continue;
            }

            if ($this->packEligible($preview)) {
                $kept[] = $id;
                continue;
            }

            $skipped[] = 'PRODUCT ' . $id . ' = SKIP / NOT ELIGIBLE / RU '
                . (string) ($preview['ruStatus'] ?? 'UNKNOWN')
                . ' / EN ' . (string) ($preview['enStatus'] ?? 'UNKNOWN')
                . ' / OVERALL ' . (string) ($preview['status'] ?? 'UNKNOWN');
        }

        $_POST['editorial_selected'] = array_map(
            static fn(int $id): string => (string) $id,
            $kept
        );

        if ($skipped !== []) {
            $this->notices[] = 'EN PACK SELECTION = FILTERED';
            $this->notices[] = 'REQUESTED = ' . count($selected);
            $this->notices[] = 'EXPORT = ' . count($kept);
            $this->notices[] = 'SKIPPED = ' . count($skipped);
            foreach ($skipped as $line) {
                $this->notices[] = $line;
            }
        }

        if ($kept === []) {
            $_POST['wp_shop_pm_editorial_action'] = '';
            $this->notices[] = 'EN PACK = NO ELIGIBLE PRODUCTS / NO WRITES';
        }
    }

    public function renderNotices(): void
    {
        if ($this->notices === []) {
            return;
        }

        echo '<div class="notice notice-warning"><pre>'
            . (string) ($this->call)('esc_html', implode("\n", $this->notices))
            . '</pre></div>';
    }

    /** @param array<string,mixed> $preview */
    private function packEligible(array $preview): bool
    {
        return ($preview['productType'] ?? 'unknown') !== 'unknown'
            && ((($preview['status'] ?? '') === 'STOP' && ($preview['enStatus'] ?? '') === 'REVIEW')
                || ($preview['status'] ?? '') === 'MIGRATE');
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
            if (is_scalar($value) && ($id = (int) $value) > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function request(string $key): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
