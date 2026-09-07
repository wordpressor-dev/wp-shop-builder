<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use Closure;
use RuntimeException;

final class VendorCoverPreviewService
{
    /** @var list<int> */
    public const DEFAULT_PRODUCT_IDS = [
        3585,
        2681,
        2995,
        3496,
        4409,
    ];

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorCoverAuditService $audit,
        private readonly VendorCoverRenderer $renderer,
        private readonly Closure $call
    ) {
    }

    /**
     * @return array{
     *   gd:bool,
     *   webp:bool,
     *   freetype:bool,
     *   ttf:bool,
     *   imagick:bool,
     *   imagickWebp:bool,
     *   engine:string,
     *   boldFont:string,
     *   regularFont:string
     * }
     */
    public function capabilities(): array
    {
        return $this->renderer->capabilities();
    }

    /**
     * @param list<int> $productIds
     * @return list<array<string, bool|int|string>>
     */
    public function generate(array $productIds): array
    {
        $productIds = $this->normalizeIds($productIds);

        if ($productIds === []) {
            throw new RuntimeException(
                'Provide between 1 and 5 Vendor product IDs.'
            );
        }

        $eligible = [];

        foreach ($this->audit->scanAll() as $row) {
            if ($row->sourceType !== 'vendor') {
                continue;
            }

            $eligible[$row->productId] = true;
        }

        $result = [];

        foreach ($productIds as $productId) {
            if (! isset($eligible[$productId])) {
                $result[] = [
                    'productId' => $productId,
                    'status' => 'SKIP',
                    'title' => '',
                    'subtitle' => '',
                    'productType' => '',
                    'url' => '',
                    'filename' => '',
                    'reason' => 'Product is not an eligible Vendor product.',
                ];

                continue;
            }

            $title = trim((string) ($this->call)(
                'get_post_field',
                'post_title',
                $productId
            ));
            $productType = trim((string) ($this->call)(
                'get_post_meta',
                $productId,
                '_wp_shop_product_type',
                true
            ));
            $subtitle = $this->subtitle($productId, $productType);
            $slug = $this->slug($title, $productId);

            $result[] = [
                'productId' => $productId,
                'status' => 'READY',
                'title' => $title,
                'subtitle' => $subtitle,
                'productType' => $productType,
                'url' => '',
                'filename' => $productId
                    . '-'
                    . $slug
                    . '-preview.webp',
                'reason' => 'Browser Canvas preview only. No server image file was written.',
            ];
        }

        return $result;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0 || in_array($productId, $result, true)) {
                continue;
            }

            $result[] = $productId;

            if (count($result) >= 5) {
                break;
            }
        }

        return $result;
    }

    private function subtitle(
        int $productId,
        string $productType
    ): string {
        $custom = $this->plainText((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_vendor_cover_subtitle',
            true
        ));

        if ($custom !== '') {
            return $this->shorten($custom, 86);
        }

        $english = $this->plainText((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_en_short_description',
            true
        ));

        if ($english !== '') {
            $sentence = preg_split(
                '/(?<=[.!?])\s+/u',
                $english,
                2
            );
            $first = is_array($sentence)
                ? trim((string) $sentence[0])
                : $english;

            return $this->shorten(
                $first !== '' ? $first : $english,
                86
            );
        }

        return strtolower(trim($productType)) === 'theme'
            ? 'Premium WordPress theme for modern websites'
            : 'Premium WordPress plugin for your website';
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = preg_replace('/\s+/u', ' ', $value);

        return is_string($value) ? trim($value) : '';
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        $cut = mb_substr(
            $value,
            0,
            max(1, $limit - 1),
            'UTF-8'
        );
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($space !== false && $space > 24) {
            $cut = mb_substr(
                $cut,
                0,
                $space,
                'UTF-8'
            );
        }

        return rtrim($cut, " \t\n\r\0\x0B,.;:-") . '…';
    }

    private function slug(string $title, int $productId): string
    {
        $slug = ($this->call)('sanitize_title', $title);
        $slug = is_string($slug) ? trim($slug) : '';

        return $slug !== ''
            ? $slug
            : 'vendor-product-' . $productId;
    }
}
