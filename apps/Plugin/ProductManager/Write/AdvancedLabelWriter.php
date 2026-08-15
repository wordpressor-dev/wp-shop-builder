<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Write;

use Closure;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

final class AdvancedLabelWriter implements ProductDraftWriterInterface
{
    public const HIT_ID = '2536';
    public const NEW_ID = '2637';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        $ids = [];
        $labels = [];

        if ($data->hit) {
            $ids[] = self::HIT_ID;
            $labels[] = 'Hit(' . self::HIT_ID . ')';
        }

        if ($data->new) {
            $ids[] = self::NEW_ID;
            $labels[] = 'New(' . self::NEW_ID . ')';
        }

        if ($ids === []) {
            ($this->call)(
                'delete_post_meta',
                $productId,
                'br_labels'
            );

            return ['ADVANCED LABELS = NONE'];
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            'br_labels',
            ['label_from_post' => $ids]
        );

        return [
            'ADVANCED LABELS = ' . implode(', ', $labels),
        ];
    }
}
