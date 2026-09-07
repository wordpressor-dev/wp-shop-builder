<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use Closure;
use WPShop\App\Plugin\ProductManager\ProductSourceType;

final class VendorCoverAuditService
{
    public const STANDARD_WIDTH = 590;
    public const STANDARD_HEIGHT = 300;
    public const STANDARD_FORMAT = 'webp';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function candidateCount(): int
    {
        return count($this->relevantProductIds());
    }

    /**
     * @return list<VendorCoverAuditRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(1000, $limit));
        $ids = array_slice($this->relevantProductIds(), $offset, $limit);
        $rows = [];

        foreach ($ids as $productId) {
            $rows[] = $this->auditProduct($productId);
        }

        return $rows;
    }

    /**
     * @return list<VendorCoverAuditRow>
     */
    public function scanAll(): array
    {
        $count = $this->candidateCount();

        if ($count === 0) {
            return [];
        }

        return $this->scan(0, $count);
    }

    private function auditProduct(int $productId): VendorCoverAuditRow
    {
        $currentTitle = trim((string) ($this->call)(
            'get_post_field',
            'post_title',
            $productId
        ));
        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $source = $this->sourceSnapshot($productId, $salesPage);
        $featuredImageId = max(
            0,
            (int) ($this->call)('get_post_thumbnail_id', $productId)
        );
        $generated = $this->truthyMeta(
            (string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_generated',
                true
            )
        );
        $locked = $this->truthyMeta(
            (string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_vendor_cover_lock',
                true
            )
        );

        $imageUrl = '';
        $imageFilename = '';
        $width = 0;
        $height = 0;
        $format = '';
        $imageStatus = 'NONE';

        if ($featuredImageId > 0) {
            $rawUrl = ($this->call)(
                'wp_get_attachment_url',
                $featuredImageId
            );
            $imageUrl = is_string($rawUrl) ? trim($rawUrl) : '';

            $rawFile = ($this->call)(
                'get_attached_file',
                $featuredImageId
            );
            $attachedFile = is_string($rawFile) ? trim($rawFile) : '';

            if ($attachedFile !== '') {
                $imageFilename = basename($attachedFile);
                $format = strtolower(
                    (string) pathinfo(
                        $attachedFile,
                        PATHINFO_EXTENSION
                    )
                );
            } elseif ($imageUrl !== '') {
                $urlPath = parse_url($imageUrl, PHP_URL_PATH);
                $urlPath = is_string($urlPath) ? $urlPath : '';
                $imageFilename = $urlPath !== ''
                    ? basename($urlPath)
                    : '';
                $format = strtolower(
                    (string) pathinfo(
                        $imageFilename,
                        PATHINFO_EXTENSION
                    )
                );
            }

            if ($format === '') {
                $mime = strtolower(trim((string) ($this->call)(
                    'get_post_mime_type',
                    $featuredImageId
                )));

                if ($mime === 'image/webp') {
                    $format = 'webp';
                } elseif (str_starts_with($mime, 'image/')) {
                    $format = substr($mime, 6);
                }
            }

            $metadata = ($this->call)(
                'wp_get_attachment_metadata',
                $featuredImageId
            );

            if (is_array($metadata)) {
                $width = max(0, (int) ($metadata['width'] ?? 0));
                $height = max(0, (int) ($metadata['height'] ?? 0));
            }

            $imageStatus = $width > 0 && $height > 0
                ? 'NON_STANDARD'
                : 'METADATA_MISSING';
        }

        $standardMatch = $featuredImageId > 0
            && $width === self::STANDARD_WIDTH
            && $height === self::STANDARD_HEIGHT
            && $format === self::STANDARD_FORMAT;

        if ($standardMatch) {
            $imageStatus = 'STANDARD';
        }

        [$action, $reason] = $this->classify(
            $source['marketplace'],
            $featuredImageId,
            $imageStatus,
            $generated,
            $locked,
            $standardMatch
        );

        return new VendorCoverAuditRow(
            $productId,
            $currentTitle,
            $salesPage,
            $source['type'],
            $source['marketplace'],
            $featuredImageId,
            $imageUrl,
            $imageFilename,
            $width,
            $height,
            $format,
            $imageStatus,
            $generated,
            $locked,
            $standardMatch,
            $action,
            $reason
        );
    }

    /**
     * @return array{string, string}
     */
    private function classify(
        bool $marketplace,
        int $featuredImageId,
        string $imageStatus,
        bool $generated,
        bool $locked,
        bool $standardMatch
    ): array {
        if ($marketplace) {
            return [
                'SKIP_MARKETPLACE',
                'ThemeForest / CodeCanyon / Envato product is protected; preserve the original featured image.',
            ];
        }

        if ($locked) {
            if ($featuredImageId <= 0) {
                return [
                    'REVIEW',
                    'Vendor cover lock is enabled but the product has no featured image.',
                ];
            }

            return [
                'KEEP',
                'Vendor cover lock is enabled; preserve the current featured image.',
            ];
        }

        if ($featuredImageId <= 0) {
            return [
                'GENERATE',
                'Vendor product has no featured image; generate the standard 590×300 WebP cover.',
            ];
        }

        if ($imageStatus === 'METADATA_MISSING') {
            return [
                'REVIEW',
                'Featured image exists but its dimensions could not be read safely.',
            ];
        }

        if ($generated) {
            if ($standardMatch) {
                return [
                    'KEEP',
                    'Generated Vendor cover already matches the 590×300 WebP standard.',
                ];
            }

            return [
                'REVIEW',
                'Product is marked as generated Vendor cover but the image no longer matches the 590×300 WebP standard.',
            ];
        }

        return [
            'GENERATE',
            'Legacy/unmanaged Vendor featured image should be replaced by the unified 590×300 WebP cover standard.',
        ];
    }

    /**
     * @return list<int>
     */
    private function relevantProductIds(): array
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        if (! is_array($ids)) {
            return [];
        }

        $result = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0) {
                continue;
            }

            $salesPage = trim((string) ($this->call)(
                'get_post_meta',
                $productId,
                'sales_page',
                true
            ));
            $source = $this->sourceSnapshot($productId, $salesPage);

            if ($source['type'] === 'other') {
                continue;
            }

            $result[] = $productId;
        }

        return $result;
    }

    /**
     * @return array{type:string, marketplace:bool}
     */
    private function sourceSnapshot(
        int $productId,
        string $salesPage
    ): array {
        $storedSource = strtolower(trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_type',
            true
        )));
        $itemId = (int) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_item_id',
            true
        );
        $sku = strtolower(trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_sku',
            true
        )));

        $marketplace = $this->isMarketplaceSalesPage($salesPage)
            || $storedSource === ProductSourceType::ENVATO
            || $itemId > 0
            || str_starts_with($sku, 'themeforest-')
            || str_starts_with($sku, 'codecanyon-');

        if ($marketplace) {
            return [
                'type' => ProductSourceType::ENVATO,
                'marketplace' => true,
            ];
        }

        if (
            $storedSource === ProductSourceType::VENDOR
            || ($storedSource === '' && $salesPage !== '')
        ) {
            return [
                'type' => ProductSourceType::VENDOR,
                'marketplace' => false,
            ];
        }

        return [
            'type' => 'other',
            'marketplace' => false,
        ];
    }

    private function isMarketplaceSalesPage(string $salesPage): bool
    {
        $host = parse_url(trim($salesPage), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return false;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        foreach (
            [
                'themeforest.net',
                'codecanyon.net',
                'envato.com',
                'elements.envato.com',
            ]
            as $domain
        ) {
            if (
                $host === $domain
                || str_ends_with($host, '.' . $domain)
            ) {
                return true;
            }
        }

        return false;
    }

    private function truthyMeta(string $value): bool
    {
        return in_array(
            strtolower(trim($value)),
            ['1', 'yes', 'true', 'on', 'locked'],
            true
        );
    }
}
