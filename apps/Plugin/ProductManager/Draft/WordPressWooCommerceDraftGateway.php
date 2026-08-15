<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use Closure;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;

final class WordPressWooCommerceDraftGateway implements
    ProductDraftGatewayInterface
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function findBySlug(
        string $slug
    ): ?ExistingProduct {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => [
                    'publish',
                    'draft',
                    'pending',
                    'private',
                    'future',
                ],
                'name' => $slug,
                'numberposts' => 1,
                'fields' => 'ids',
                'suppress_filters' => true,
            ]
        );

        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $id = (int) $ids[0];

        if ($id <= 0) {
            return null;
        }

        return new ExistingProduct(
            $id,
            $this->status($id)
        );
    }

    public function findBySku(
        string $sku
    ): ?ExistingProduct {
        if ($sku === '') {
            return null;
        }

        $id = (int) ($this->call)(
            'wc_get_product_id_by_sku',
            $sku
        );

        if ($id <= 0) {
            return null;
        }

        return new ExistingProduct(
            $id,
            $this->status($id)
        );
    }

    public function createCore(
        ProductDraftData $data
    ): int {
        $localDate = $data->sourceUpdateDate
            . ' 12:00:00';
        $gmtDate = (string) ($this->call)(
            'get_gmt_from_date',
            $localDate
        );

        $result = ($this->call)(
            'wp_insert_post',
            [
                'post_type' => 'product',
                'post_status' => 'draft',
                'post_title' => $data->title(),
                'post_name' => $data->slug,
                'post_content' => $data->longDescription,
                'post_excerpt' => $data->shortDescription,
                'post_date' => $localDate,
                'post_date_gmt' => $gmtDate,
            ],
            true
        );

        if (($this->call)('is_wp_error', $result)) {
            throw new RuntimeException(
                $this->errorMessage($result)
            );
        }

        $productId = (int) $result;

        if ($productId <= 0) {
            throw new RuntimeException(
                'wp_insert_post returned an invalid product ID.'
            );
        }

        ($this->call)(
            'wp_set_object_terms',
            $productId,
            'simple',
            'product_type',
            false
        );

        foreach ($this->coreMeta($data) as $key => $value) {
            ($this->call)(
                'update_post_meta',
                $productId,
                $key,
                $value
            );
        }

        if ($data->downloadUrl !== '') {
            $downloadId = md5($data->downloadUrl);

            ($this->call)(
                'update_post_meta',
                $productId,
                '_downloadable_files',
                [
                    $downloadId => [
                        'name' => $data->skuFilename,
                        'file' => $data->downloadUrl,
                    ],
                ]
            );
        }

        if ($data->featuredImageId > 0) {
            ($this->call)(
                'set_post_thumbnail',
                $productId,
                $data->featuredImageId
            );
        }

        return $productId;
    }

    private function status(int $productId): string
    {
        $status = ($this->call)(
            'get_post_status',
            $productId
        );

        return is_string($status) && $status !== ''
            ? $status
            : 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function coreMeta(
        ProductDraftData $data
    ): array {
        return [
            '_regular_price' => $data->price,
            '_price' => $data->price,
            '_virtual' => 'yes',
            '_downloadable' => 'yes',
            '_download_limit' => '-1',
            '_download_expiry' => '10',
            '_sku' => $data->skuFilename,
            '_manage_stock' => 'no',
            '_stock_status' => 'instock',
        ];
    }

    private function errorMessage(mixed $error): string
    {
        if (
            is_object($error)
            && method_exists($error, 'get_error_message')
        ) {
            $message = $error->get_error_message();

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'WordPress product creation failed.';
    }
}
