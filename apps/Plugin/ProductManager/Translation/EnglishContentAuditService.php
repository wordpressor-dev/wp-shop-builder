<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;

final class EnglishContentAuditService
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly DatabaseConnectionInterface $database
    ) {
    }

    public function candidateCount(): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        return is_array($ids) ? count($ids) : 0;
    }

    /**
     * @return list<EnglishContentAuditRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(50, $limit));
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        if (! is_array($ids)) {
            return [];
        }

        $productIds = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }

        if ($productIds === []) {
            return [];
        }

        [$translationIssues, $trpChecked] = $this->translationIssues(
            $productIds
        );
        $rows = [];

        foreach ($productIds as $productId) {
            $title = trim((string) ($this->call)(
                'get_post_field',
                'post_title',
                $productId
            ));
            $issues = [];
            $locations = [];

            foreach (
                [
                    'SHORT' => '_wp_shop_en_short_description',
                    'LONG' => '_wp_shop_en_long_description',
                    'META' => '_wp_shop_en_meta_description',
                ] as $label => $key
            ) {
                $value = trim((string) ($this->call)(
                    'get_post_meta',
                    $productId,
                    $key,
                    true
                ));

                if ($value === '') {
                    $issues[] = 'EN_' . $label . '_MISSING';
                    $locations[] = $label;
                } elseif ($this->hasCyrillic($value)) {
                    $issues[] = 'EN_' . $label . '_CYRILLIC';
                    $locations[] = $label;
                }
            }

            foreach ($translationIssues[$productId] ?? [] as $issue) {
                $issues[] = $issue;
                $locations[] = 'TRP';
            }

            $issues = array_values(array_unique($issues));
            $locations = array_values(array_unique($locations));

            $rows[] = new EnglishContentAuditRow(
                $productId,
                $title !== '' ? $title : 'Product #' . $productId,
                $issues === [] ? 'CLEAN' : 'REVIEW',
                $locations,
                $issues,
                $trpChecked
            );
        }

        return $rows;
    }

    /**
     * @param list<int> $productIds
     * @return array{array<int, list<string>>, bool}
     */
    private function translationIssues(array $productIds): array
    {
        $dictionaryTable = $this->tableLike(
            '%trp_dictionary_ru_ru_en_us'
        );
        $metaTable = $this->tableLike('%trp_original_meta');

        if ($dictionaryTable === null || $metaTable === null) {
            return [[], false];
        }

        $originalToProducts = [];

        foreach (array_chunk($productIds, 100) as $chunk) {
            $placeholders = implode(
                ',',
                array_fill(0, count($chunk), '%s')
            );
            $sql = sprintf(
                'SELECT original_id, meta_value FROM %s '
                . 'WHERE meta_key = %%s AND meta_value IN (%s)',
                $metaTable,
                $placeholders
            );
            $parameters = array_merge(
                ['post_parent_id'],
                array_map('strval', $chunk)
            );

            foreach ($this->database->fetchAll($sql, $parameters) as $row) {
                $originalId = (int) ($row['original_id'] ?? 0);
                $productId = (int) ($row['meta_value'] ?? 0);

                if ($originalId <= 0 || $productId <= 0) {
                    continue;
                }

                $originalToProducts[$originalId] ??= [];
                $originalToProducts[$originalId][] = $productId;
            }
        }

        if ($originalToProducts === []) {
            return [[], true];
        }

        $counts = [];

        foreach (
            array_chunk(array_keys($originalToProducts), 100)
            as $chunk
        ) {
            $placeholders = implode(
                ',',
                array_fill(0, count($chunk), '%d')
            );
            $sql = sprintf(
                'SELECT original_id, translated, status FROM %s '
                . 'WHERE original_id IN (%s)',
                $dictionaryTable,
                $placeholders
            );

            foreach ($this->database->fetchAll($sql, $chunk) as $row) {
                $originalId = (int) ($row['original_id'] ?? 0);
                $translated = trim(
                    (string) ($row['translated'] ?? '')
                );
                $status = (int) ($row['status'] ?? 0);

                foreach ($originalToProducts[$originalId] ?? [] as $productId) {
                    $counts[$productId] ??= [
                        'cyrillic' => 0,
                        'incomplete' => 0,
                    ];

                    if ($this->hasCyrillic($translated)) {
                        ++$counts[$productId]['cyrillic'];
                    }

                    if ($translated === '' || $status !== 2) {
                        ++$counts[$productId]['incomplete'];
                    }
                }
            }
        }

        $issues = [];

        foreach ($counts as $productId => $productCounts) {
            if ($productCounts['cyrillic'] > 0) {
                $issues[$productId][] = 'TRP_CYRILLIC_'
                    . $productCounts['cyrillic'];
            }

            if ($productCounts['incomplete'] > 0) {
                $issues[$productId][] = 'TRP_INCOMPLETE_'
                    . $productCounts['incomplete'];
            }
        }

        return [$issues, true];
    }

    private function tableLike(string $pattern): ?string
    {
        $row = $this->database->fetchOne(
            'SHOW TABLES LIKE %s',
            [$pattern]
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
                preg_match('/^[A-Za-z0-9_]+$/D', $table) === 1
                && (
                    str_ends_with(
                        $table,
                        'trp_dictionary_ru_ru_en_us'
                    )
                    || str_ends_with($table, 'trp_original_meta')
                )
            ) {
                return $table;
            }
        }

        return null;
    }

    private function hasCyrillic(string $value): bool
    {
        return preg_match('/[А-Яа-яЁё]/u', $value) === 1;
    }
}
