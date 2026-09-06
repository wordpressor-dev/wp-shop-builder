<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;

final class TranslatePressTitleInspector
{
    private ?string $table = null;

    public function __construct(
        private readonly DatabaseConnectionInterface $database
    ) {
    }

    public function inspect(string $source): ProductTitleTranslationSnapshot
    {
        $source = trim($source);

        if ($source === '') {
            return new ProductTitleTranslationSnapshot(
                'EMPTY_SOURCE',
                []
            );
        }

        $table = $this->table();

        if ($table === null) {
            return new ProductTitleTranslationSnapshot(
                'TRP_TABLE_MISSING',
                []
            );
        }

        $variants = $this->sourceVariants($source);
        $placeholders = implode(
            ',',
            array_fill(0, count($variants), '%s')
        );
        $sql = sprintf(
            'SELECT original, translated, status '
            . 'FROM %s WHERE original IN (%s) ORDER BY id DESC',
            $table,
            $placeholders
        );
        $rows = $this->database->fetchAll(
            $sql,
            $variants
        );

        if ($rows === []) {
            return new ProductTitleTranslationSnapshot(
                'NOT_FOUND',
                []
            );
        }

        $translations = [];
        $unfinished = false;

        foreach ($rows as $row) {
            $translated = trim(
                (string) ($row['translated'] ?? '')
            );
            $finished = (int) ($row['status'] ?? 0) === 2
                && $translated !== '';

            if (! $finished) {
                $unfinished = true;
                continue;
            }

            $translations[] = $translated;
        }

        $translations = array_values(array_unique(
            $translations
        ));

        if ($translations !== []) {
            return new ProductTitleTranslationSnapshot(
                count($translations) === 1
                    ? 'TRANSLATED'
                    : 'MULTIPLE_TRANSLATIONS',
                $translations
            );
        }

        return new ProductTitleTranslationSnapshot(
            $unfinished ? 'UNFINISHED' : 'NOT_FOUND',
            []
        );
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
     * @return list<string>
     */
    private function sourceVariants(string $source): array
    {
        $base = [
            $source,
            str_replace('&', '&amp;', $source),
            str_replace('&', '&#038;', $source),
            str_replace('&', '&#38;', $source),
        ];
        $variants = [];

        foreach (array_unique($base) as $value) {
            $variants[] = $value;
            $variants[] = ' ' . $value;
            $variants[] = $value . ' ';
            $variants[] = ' ' . $value . ' ';
        }

        return array_values(array_unique($variants));
    }
}
