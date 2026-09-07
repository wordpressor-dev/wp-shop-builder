<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover\Contracts;

use WPShop\App\Plugin\ProductManager\Cover\VendorAiImageResult;

interface VendorAiImageGeneratorInterface
{
    public function configured(): bool;

    public function generate(string $prompt): VendorAiImageResult;
}
