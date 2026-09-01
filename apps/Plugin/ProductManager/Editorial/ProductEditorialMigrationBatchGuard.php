<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use Closure;
use InvalidArgumentException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class ProductEditorialMigrationBatchGuard
{
    private const PAGE_SLUG = 'wp-shop-builder-product-editorial-migration';
    private const PACK_VERSION = '2';

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
        ($this->call)('add_action', 'admin_init', [$this, 'guardRequest'], 1);
        ($this->call)('add_action', 'admin_notices', [$this, 'renderNotices']);
    }

    public function guardRequest(): void
    {
        if ($this->request('page') !== self::PAGE_SLUG) {
            return;
        }

        if (! (bool) ($this->call)('current_user_can', 'manage_woocommerce')) {
            return;
        }

        $action = $this->request('wp_shop_pm_editorial_action');
        if ($action === 'prepare_en_pack') {
            $this->filterSelectedForPack();
            return;
        }

        if ($action === 'apply_selected') {
            $this->filterSelectedForApply();
            return;
        }

        if ($action === 'import_en_pack') {
            $this->filterImportPack();
        }
    }

    public function renderNotices(): void
    {
        if ($this->notices === []) {
            return;
        }

        echo '<div class="notice notice-warning"><pre>'
            . $this->escape(implode("\n", $this->notices))
            . '</pre></div>';
    }

    private function filterSelectedForPack(): void
    {
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

            $skipped[] = 'PRODUCT ' . $id . ' = SKIP / NOT ELIGIBLE / STATUS '
                . $preview['status']
                . ' / EN ' . $preview['enStatus'];
        }

        $this->replaceSelected($kept);
        $this->report('EN PACK PRE-FILTER', $selected, $kept, $skipped);
        $this->disableActionWhenEmpty($kept, 'EN PACK = NO ELIGIBLE PRODUCTS / NO WRITES');
    }

    private function filterSelectedForApply(): void
    {
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

            $status = $preview['status'];
            if ($status === 'MIGRATE') {
                $kept[] = $id;
                continue;
            }

            if ($status === 'CURRENT') {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP CURRENT';
                continue;
            }

            $skipped[] = 'PRODUCT ' . $id . ' = SKIP / NOT READY / STATUS ' . $status;
        }

        $this->replaceSelected($kept);
        $this->report('APPLY SELECTED PRE-FILTER', $selected, $kept, $skipped);
        $this->disableActionWhenEmpty($kept, 'APPLY SELECTED = NO MIGRATE PRODUCTS / NO WRITES');
    }

    private function filterImportPack(): void
    {
        if (! isset($_FILES['editorial_en_pack'])) {
            return;
        }
        $upload = $_FILES['editorial_en_pack'];

        $tmp = is_scalar($upload['tmp_name'] ?? null) ? (string) $upload['tmp_name'] : '';
        if ($tmp === '' || ! is_file($tmp)) {
            return;
        }

        $stream = fopen($tmp, 'rb');
        if ($stream === false) {
            return;
        }

        try {
            $headers = fgetcsv($stream, 0, ';', '"', '');
            if (! is_array($headers)) {
                return;
            }
            $headers = array_map(
                static fn(mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $headers
            );
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            $productIdColumn = array_search('Product ID', $headers, true);
            if ($productIdColumn === false) {
                return;
            }

            $rows = [];
            while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                if ($this->rowEmpty($row)) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    return;
                }
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            return;
        }

        $builder = new TranslationMapBuilder();
        $kept = [];
        $skipped = [];
        $seen = [];

        foreach ($rows as $row) {
            $mapped = array_combine($headers, $row);

            $id = (int) trim((string) ($mapped['Product ID'] ?? '0'));
            if ($id <= 0) {
                $skipped[] = 'PRODUCT ? = SKIP / INVALID PRODUCT ID';
                continue;
            }
            if (isset($seen[$id])) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / DUPLICATE ROW';
                continue;
            }
            $seen[$id] = true;

            if (trim((string) ($mapped['Pack Version'] ?? '')) !== self::PACK_VERSION) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / UNSUPPORTED PACK VERSION';
                continue;
            }

            try {
                $preview = $this->migration->preview($id);
            } catch (Throwable $exception) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / PREVIEW ERROR: ' . $exception->getMessage();
                continue;
            }

            if (! $this->packEligible($preview)) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / NOT ELIGIBLE / STATUS '
                    . $preview['status']
                    . ' / EN ' . $preview['enStatus'];
                continue;
            }

            if ((string) ($mapped['Type'] ?? '') !== $preview['productType']) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / TYPE CHANGED / RE-EXPORT';
                continue;
            }

            $current = $preview['current'];
            $target = $preview['generated'];

            if (! hash_equals(
                $this->fingerprint($current),
                trim((string) ($mapped['Source RU Fingerprint'] ?? ''))
            )) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / SOURCE RU CHANGED / RE-EXPORT';
                continue;
            }

            if (! hash_equals(
                $this->fingerprint($target),
                trim((string) ($mapped['Target RU Fingerprint'] ?? ''))
            )) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / TARGET RU CHANGED / RE-EXPORT';
                continue;
            }

            $targetColumns = [
                'Target RU Short HTML' => 'ruShort',
                'Target RU Long HTML' => 'ruLong',
                'Target RU Meta' => 'ruMeta',
            ];
            $targetModified = false;
            foreach ($targetColumns as $column => $field) {
                if ((string) ($mapped[$column] ?? '') !== $target[$field]) {
                    $targetModified = true;
                    break;
                }
            }
            if ($targetModified) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / TARGET RU COLUMNS MODIFIED';
                continue;
            }

            try {
                $builder->build(
                    $target['ruShort'],
                    $target['ruLong'],
                    $target['ruMeta'],
                    trim((string) ($mapped['EN Short HTML'] ?? '')),
                    trim((string) ($mapped['EN Long HTML'] ?? '')),
                    trim((string) ($mapped['EN Meta'] ?? ''))
                );
            } catch (InvalidArgumentException $exception) {
                $skipped[] = 'PRODUCT ' . $id . ' = SKIP / TRANSLATION INVALID: '
                    . $exception->getMessage();
                continue;
            }

            $kept[] = $row;
        }

        $keptIds = [];
        foreach ($kept as $row) {
            $keptIds[] = (int) $row[$productIdColumn];
        }
        $originalIds = [];
        foreach ($rows as $row) {
            $originalIds[] = isset($row[$productIdColumn]) ? (int) $row[$productIdColumn] : 0;
        }
        $this->report('EN PACK IMPORT PRE-FILTER', $originalIds, $keptIds, $skipped);

        if ($kept === []) {
            $_POST['wp_shop_pm_editorial_action'] = '';
            $this->notices[] = 'EN PACK IMPORT = NO ELIGIBLE ROWS / NO WRITES';
            return;
        }

        $write = fopen($tmp, 'wb');
        if ($write === false) {
            $this->notices[] = 'EN PACK IMPORT GUARD = COULD NOT REWRITE TEMP CSV / ORIGINAL VALIDATION WILL RUN';
            return;
        }

        try {
            fwrite($write, "\xEF\xBB\xBF");
            fputcsv($write, $headers, ';', '"', '');
            foreach ($kept as $row) {
                fputcsv($write, $row, ';', '"', '');
            }
        } finally {
            fclose($write);
        }

        clearstatcache(true, $tmp);
        $_FILES['editorial_en_pack']['size'] = filesize($tmp) ?: 0;
    }

    /** @param array<string,mixed> $preview */
    private function packEligible(array $preview): bool
    {
        return ($preview['productType'] ?? 'unknown') !== 'unknown'
            && ((($preview['status'] ?? '') === 'STOP' && ($preview['enStatus'] ?? '') === 'REVIEW')
                || ($preview['status'] ?? '') === 'MIGRATE');
    }

    /** @param array<string,mixed> $content */
    private function fingerprint(array $content): string
    {
        return hash(
            'sha256',
            (string) $content['ruShort'] . "\0"
                . (string) $content['ruLong'] . "\0"
                . (string) $content['ruMeta']
        );
    }

    /** @param array<int,mixed> $row */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
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

    /** @param list<int> $ids */
    private function replaceSelected(array $ids): void
    {
        $_POST['editorial_selected'] = array_map(static fn(int $id): string => (string) $id, $ids);
    }

    /**
     * @param list<int> $original
     * @param list<int> $kept
     * @param list<string> $skipped
     */
    private function report(string $label, array $original, array $kept, array $skipped): void
    {
        if ($skipped === []) {
            return;
        }

        $this->notices[] = $label . ' = FILTERED';
        $this->notices[] = 'REQUESTED = ' . count($original);
        $this->notices[] = 'CONTINUE = ' . count($kept);
        $this->notices[] = 'SKIPPED = ' . count($skipped);
        foreach ($skipped as $line) {
            $this->notices[] = $line;
        }
    }

    /** @param list<int> $kept */
    private function disableActionWhenEmpty(array $kept, string $message): void
    {
        if ($kept !== []) {
            return;
        }

        $_POST['wp_shop_pm_editorial_action'] = '';
        $this->notices[] = $message;
    }

    private function request(string $key): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function escape(string $value): string
    {
        return (string) ($this->call)('esc_html', $value);
    }
}
