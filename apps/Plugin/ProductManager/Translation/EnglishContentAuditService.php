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

        $trpChecked = $this->translationTablesAvailable();
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

    private function translationTablesAvailable(): bool
    {
        return $this->tableLike('%trp_dictionary_ru_ru_en_us') !== null
            && $this->tableLike('%trp_original_meta') !== null;
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
