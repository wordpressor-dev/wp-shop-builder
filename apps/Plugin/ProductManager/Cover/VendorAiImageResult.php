<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

final readonly class VendorAiImageResult
{
    public function __construct(
        public string $bytes,
        public string $mimeType = 'image/png'
    ) {
    }
}
