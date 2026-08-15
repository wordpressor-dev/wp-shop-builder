<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

final readonly class EnvatoItem
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed> $source
     */
    public function __construct(
        public int $itemId,
        public string $baseTitle,
        public string $productSlug,
        public string $version,
        public string $updatedDate,
        public string $developer,
        public string $salesPage,
        public int $sales,
        public ?string $publishedAt,
        public array $tags,
        public string $skuFilename,
        public array $source
    ) {
    }
}
