<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

final readonly class ProductArchiveUploadResult
{
    /**
     * @param list<string> $logs
     */
    public function __construct(
        public bool $success,
        public bool $supplied,
        public string $skuFilename,
        public string $downloadUrl,
        public string $targetPath,
        public string $backupPath,
        public array $logs
    ) {
    }
}
