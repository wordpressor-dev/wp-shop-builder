<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;

final class TranslatePressDictionary implements
    TranslationDictionaryInterface
{
    private ?string $table = null;

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly DatabaseConnectionInterface $database,
        private readonly Closure $call,
        private readonly TranslationMapBuilder $mapBuilder
    ) {
    }

    public function status(array $map): TranslationDictionaryStatus
    {
        $table = $this->table();

        if ($table === null) {
            return new TranslationDictionaryStatus(
                false,
                count($map),
                0,
                0,
                0,
                count($map),
                []
            );
        }

        $rows = $this->rows($table, $map);
        $exact = 0;
        $keep = 0;
        $fill = 0;
        $missing = 0;
        $items = [];

        foreach ($map as $source => $target) {
            $row = $rows[$source] ?? null;

            if (! is_array($row)) {
                $action = 'MISSING';
                $missing++;
            } elseif (
                (int) ($row['status'] ?? 0) === 2
                && trim((string) ($row['translated'] ?? '')) !== ''
            ) {
                if (
                    $this->mapBuilder->normalize(
                        (string) $row['translated']
                    ) === $this->mapBuilder->normalize($target)
                ) {
                    $action = 'EXACT';
                    $exact++;
                } else {
                    $action = 'KEEP';
                    $keep++;
                }
            } else {
                $action = 'FILL';
                $fill++;
            }

            $items[] = [
                'source' => $source,
                'target' => $target,
                'row' => $row,
                'action' => $action,
            ];
        }

        return new TranslationDictionaryStatus(
            true,
            count($map),
            $exact,
            $keep,
            $fill,
            $missing,
            $items
        );
    }

    public function backup(
        int $productId,
        string $slug,
        TranslationDictionaryStatus $status
    ): void {
        $key = 'wp_shop_pm_v14_trp_backup_' . $productId;
        $existing = ($this->call)(
            'get_option',
            $key,
            false
        );

        if ($existing !== false) {
            return;
        }

        $rows = [];

        foreach ($status->items as $item) {
            if (
                $item['action'] === 'FILL'
                && is_array($item['row'])
            ) {
                $rows[] = $item['row'];
            }
        }

        ($this->call)(
            'add_option',
            $key,
            [
                'product_id' => $productId,
                'slug' => $slug,
                'created_at' => (string) ($this->call)(
                    'current_time',
                    'mysql'
                ),
                'rows' => $rows,
            ],
            '',
            false
        );
    }

    public function fill(
        TranslationDictionaryStatus $status
    ): int {
        $table = $this->table();

        if ($table === null) {
            throw new RuntimeException(
                'TranslatePress dictionary table was not found.'
            );
        }

        $filled = 0;

        foreach ($status->items as $item) {
            if ($item['action'] !== 'FILL') {
                continue;
            }

            $row = $item['row'];

            if (! is_array($row) || ! isset($row['id'])) {
                throw new RuntimeException(
                    'TranslatePress FILL row has no ID.'
                );
            }

            $this->database->update(
                $table,
                [
                    'translated' => $item['target'],
                    'status' => 2,
                ],
                ['id' => (int) $row['id']],
                ['%s', '%d'],
                ['%d']
            );
            $filled++;
        }

        return $filled;
    }

    private function table(): ?string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $row = $this->database->fetchOne(
            'SHOW TABLES LIKE %s',
            ['%trp_dictionary_ru_ru_en_us']
        );

        if ($row === null) {
            return null;
        }

        foreach ($row as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $table = (string) $value;

            if (
                str_ends_with(
                    $table,
                    'trp_dictionary_ru_ru_en_us'
                )
                && preg_match(
                    '/^[A-Za-z0-9_]+$/D',
                    $table
                ) === 1
            ) {
                $this->table = $table;

                return $table;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $map
     * @return array<string, array<string, mixed>>
     */
    private function rows(
        string $table,
        array $map
    ): array {
        if ($map === []) {
            return [];
        }

        $variants = [];

        foreach (array_keys($map) as $source) {
            foreach ($this->sourceVariants($source) as $variant) {
                $variants[] = $variant;
            }
        }

        $variants = array_values(array_unique($variants));
        $byNormalized = [];

        foreach (array_chunk($variants, 100) as $chunk) {
            $placeholders = implode(
                ',',
                array_fill(0, count($chunk), '%s')
            );
            $sql = sprintf(
                'SELECT id, original, translated, status, original_id '
                . 'FROM %s WHERE original IN (%s) ORDER BY id DESC',
                $table,
                $placeholders
            );
            $rows = $this->database->fetchAll(
                $sql,
                $chunk
            );

            foreach ($rows as $row) {
                $normalized = $this->mapBuilder->normalize(
                    (string) ($row['original'] ?? '')
                );

                if (
                    $normalized === ''
                    || isset($byNormalized[$normalized])
                ) {
                    continue;
                }

                $byNormalized[$normalized] = $row;
            }
        }

        $result = [];

        foreach (array_keys($map) as $source) {
            $normalized = $this->mapBuilder->normalize($source);

            if (isset($byNormalized[$normalized])) {
                $result[$source] = $byNormalized[$normalized];
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function sourceVariants(string $source): array
    {
        $trimmed = trim($source);
        $base = [
            $trimmed,
            str_replace('&', '&amp;', $trimmed),
            str_replace('&', '&#038;', $trimmed),
            str_replace('&', '&#38;', $trimmed),
        ];
        $variants = [];

        foreach (array_unique($base) as $value) {
            $variants[] = $value;
            $variants[] = ' ' . $value;
            $variants[] = $value . ' ';
            $variants[] = ' ' . $value . ' ';
        }

        $variants[] = $source;

        return array_values(array_unique($variants));
    }
}
