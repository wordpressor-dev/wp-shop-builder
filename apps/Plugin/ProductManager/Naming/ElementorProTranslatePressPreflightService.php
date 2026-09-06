<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;

final class ElementorProTranslatePressPreflightService
{
    private const PRODUCT_ID = 3585;

    private const CURRENT_TITLE =
        'Elementor Pro Website Builder – More Than Just a Page Builder';

    private const TARGET_TITLE = 'Elementor Pro';

    public function __construct(
        private readonly TranslatePressTitleInspector $titleInspector,
        private readonly TranslationDictionaryInterface $dictionary
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $current = $this->titleInspector->inspect(
            self::CURRENT_TITLE
        );
        $target = $this->titleInspector->inspect(
            self::TARGET_TITLE
        );
        $status = $this->dictionary->status([
            self::TARGET_TITLE => self::TARGET_TITLE,
        ]);

        $rows = [];

        foreach ($status->items as $item) {
            foreach ((array) ($item['rows'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'original' => (string) ($row['original'] ?? ''),
                    'translated' => (string) ($row['translated'] ?? ''),
                    'status' => (int) ($row['status'] ?? 0),
                    'block_type' => (int) ($row['block_type'] ?? 0),
                    'original_id' => (int) ($row['original_id'] ?? 0),
                ];
            }
        }

        return [
            'productId' => self::PRODUCT_ID,
            'currentTitle' => self::CURRENT_TITLE,
            'targetTitle' => self::TARGET_TITLE,
            'currentState' => $current->state,
            'currentTranslations' => $current->translations,
            'targetInspectorState' => $target->state,
            'targetInspectorTranslations' => $target->translations,
            'tableOk' => $status->tableOk,
            'total' => $status->total,
            'exact' => $status->exact,
            'keep' => $status->keep,
            'fill' => $status->fill,
            'missing' => $status->missing,
            'rows' => $rows,
            'safeAction' => $this->safeAction($status),
        ];
    }

    private function safeAction(
        \WPShop\App\Plugin\ProductManager\Translation\TranslationDictionaryStatus $status
    ): string {
        if (
            $status->tableOk
            && $status->total === 1
            && $status->exact === 1
            && $status->keep === 0
            && $status->fill === 0
            && $status->missing === 0
        ) {
            return 'TARGET_ALREADY_EXACT';
        }

        if (
            $status->tableOk
            && $status->keep === 0
            && $status->fill > 0
            && $status->missing === 0
        ) {
            return 'SAFE_FILL_POSSIBLE';
        }

        if ($status->keep > 0) {
            return 'STOP_FINISHED_CONFLICT';
        }

        if ($status->missing > 0) {
            return 'STOP_TARGET_MISSING';
        }

        return 'STOP_REVIEW';
    }
}
