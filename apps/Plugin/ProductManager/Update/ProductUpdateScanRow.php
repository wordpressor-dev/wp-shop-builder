<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

final readonly class ProductUpdateScanRow
{
    public function __construct(
        public int $productId,
        public string $title,
        public string $currentVersion,
        public string $envatoVersion,
        public string $envatoUpdateDate,
        public string $status,
        public string $message
    ) {
    }
}
