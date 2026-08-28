<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;
use InvalidArgumentException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;

final class TranslatePressProductTranslator
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly TranslationMapBuilder $mapBuilder,
        private readonly TranslationDictionaryInterface $dictionary,
        private readonly TranslationRegistrarInterface $registrar,
        private readonly Closure $call
    ) {
    }

    public function translate(
        int $productId,
        string $enShort,
        string $enLong,
        string $enMeta
    ): ProductTranslationResult {
        $product = ($this->call)(
            'get_post',
            $productId
        );
        $post = is_object($product)
            ? get_object_vars($product)
            : [];

        if (
            ($post['post_type'] ?? null) !== 'product'
            || ! isset($post['ID'])
        ) {
            return new ProductTranslationResult(
                false,
                ['PRODUCT_NOT_FOUND']
            );
        }

        $status = (string) ($post['post_status'] ?? '');

        if ($status !== 'publish') {
            return new ProductTranslationResult(
                false,
                [
                    'PUBLISH_FIRST; CURRENT_STATUS_'
                    . strtoupper($status),
                ]
            );
        }

        $productId = (int) $post['ID'];
        $slug = (string) ($post['post_name'] ?? '');
        $ruShort = (string) ($post['post_excerpt'] ?? '');
        $ruLong = (string) ($post['post_content'] ?? '');
        $sureRank = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );
        $ruMeta = is_array($sureRank)
            ? (string) ($sureRank['page_description'] ?? '')
            : '';

        $this->savePreparedEnglish(
            $productId,
            $enShort,
            $enLong,
            $enMeta
        );

        try {
            $map = $this->mapBuilder->build(
                $ruShort,
                $ruLong,
                $ruMeta,
                $enShort,
                $enLong,
                $enMeta
            );
            $map = $this->withCatalogDisplayTranslations(
                $productId,
                $map
            );
        } catch (InvalidArgumentException $exception) {
            return new ProductTranslationResult(
                false,
                [
                    'MAP_ERROR: ' . $exception->getMessage(),
                ]
            );
        }

        $logs = [
            'RU CONTENT = PRESERVED',
            'TRANSLATION SEGMENTS = ' . count($map),
        ];

        try {
            $logs[] = $this->registrar->registerPage($slug);
            $translationStatus = $this->dictionary->status($map);

            if ($translationStatus->missing > 0) {
                $logs[] = 'RETRY_'
                    . $this->registrar->registerPage($slug);
                $translationStatus = $this->dictionary->status($map);
            }

            if (
                $translationStatus->tableOk
                && $translationStatus->missing > 0
            ) {
                $logs = array_merge(
                    $logs,
                    $this->registrar->registerMissing(
                        $translationStatus
                    )
                );
                $translationStatus = $this->dictionary->status($map);
            }

            if (! $translationStatus->tableOk) {
                $logs[] = 'TRP_TABLE_NOT_FOUND';

                return new ProductTranslationResult(
                    false,
                    $logs,
                    $translationStatus
                );
            }

            if ($translationStatus->missing > 0) {
                $logs[] = 'REVIEW_MISSING_'
                    . $translationStatus->missing;
                $logs = array_merge(
                    $logs,
                    $this->registrar->missingDebugLines(
                        $translationStatus
                    )
                );

                return new ProductTranslationResult(
                    false,
                    $logs,
                    $translationStatus
                );
            }

            $this->dictionary->backup(
                $productId,
                $slug,
                $translationStatus
            );
            $filled = $this->dictionary->fill(
                $translationStatus
            );
            $logs[] = 'EN FILLED = ' . $filled;
            $final = $this->dictionary->status($map);
        } catch (Throwable $exception) {
            $logs[] = 'TRANSLATION_ERROR: '
                . $exception->getMessage();

            return new ProductTranslationResult(
                false,
                $logs
            );
        }

        $logs[] = 'EXACT = ' . $final->exact;
        $logs[] = 'KEPT = ' . $final->keep;
        $logs[] = 'FILL = ' . $final->fill;
        $logs[] = 'MISSING = ' . $final->missing;
        $logs[] = $final->ready()
            ? 'OVERALL = READY'
            : 'OVERALL = REVIEW';

        return new ProductTranslationResult(
            $final->ready(),
            $logs,
            $final
        );
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    private function withCatalogDisplayTranslations(
        int $productId,
        array $map
    ): array {
        $category = ($this->call)(
            'get_post_meta',
            $productId,
            'attr_category_value',
            true
        );

        if (! is_string($category)) {
            return $map;
        }

        $category = trim($category);
        $english = match ($category) {
            'Темы' => 'Themes',
            'Плагины' => 'Plugins',
            'Шаблоны' => 'Templates',
            default => '',
        };

        if ($english !== '') {
            $map[$category] = $english;
        }

        return $map;
    }

    private function savePreparedEnglish(
        int $productId,
        string $enShort,
        string $enLong,
        string $enMeta
    ): void {
        $safeShort = (string) ($this->call)(
            'wp_kses_post',
            $enShort
        );
        $safeLong = (string) ($this->call)(
            'wp_kses_post',
            $enLong
        );
        $safeMeta = (string) ($this->call)(
            'sanitize_textarea_field',
            $enMeta
        );

        foreach (
            [
                '_wp_shop_en_short_description' => $safeShort,
                '_wp_shop_en_long_description' => $safeLong,
                '_wp_shop_en_meta_description' => $safeMeta,
            ] as $key => $value
        ) {
            ($this->call)(
                'update_post_meta',
                $productId,
                $key,
                $value
            );
        }
    }
}
