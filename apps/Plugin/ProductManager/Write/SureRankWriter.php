<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Write;

use Closure;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

final class SureRankWriter implements ProductDraftWriterInterface
{
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
        $settings = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );

        if (! is_array($settings)) {
            $settings = [];
        }

        $settings['page_description'] =
            $data->metaDescription;

        ($this->call)(
            'update_post_meta',
            $productId,
            'surerank_settings_general',
            $settings
        );

        return [
            'SURERANK META DESCRIPTION = UPDATED',
        ];
    }
}
