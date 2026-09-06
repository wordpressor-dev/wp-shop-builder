<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use RuntimeException;

final class ProductTitleVersionMigrationService
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    /**
     * @param array<string, mixed> $expected
     * @return array{
     *   productId:int,
     *   status:string,
     *   oldTitle:string,
     *   newTitle:string,
     *   slug:string,
     *   reason:string
     * }
     */
    public function apply(array $expected): array
    {
        $productId = (int) ($expected['productId'] ?? 0);
        $expectedTitle = trim((string) ($expected['currentTitle'] ?? ''));
        $expectedVersion = trim((string) ($expected['storedVersion'] ?? ''));
        $expectedRecommended = trim(
            (string) ($expected['recommendedTitle'] ?? '')
        );

        if (
            $productId <= 0
            || (string) ($expected['action'] ?? '') !== 'REMOVE_VERSION'
        ) {
            return $this->result(
                $productId,
                'SKIP',
                '',
                '',
                '',
                'Saved audit row is not an eligible REMOVE_VERSION item.'
            );
        }

        $post = ($this->call)('get_post', $productId);

        if (! is_object($post)) {
            return $this->result(
                $productId,
                'SKIP',
                '',
                '',
                '',
                'Product no longer exists.'
            );
        }

        $postType = trim((string) ($post->post_type ?? ''));
        $postStatus = trim((string) ($post->post_status ?? ''));
        $currentTitle = trim((string) ($post->post_title ?? ''));
        $slug = trim((string) ($post->post_name ?? ''));

        if ($postType !== 'product' || $postStatus !== 'publish') {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Product is no longer a published WooCommerce product.'
            );
        }

        $currentVersion = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'attr_version_value',
            true
        ));

        if ($currentTitle !== $expectedTitle) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Current title changed after the audit.'
            );
        }

        if ($currentVersion !== $expectedVersion) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Stored version changed after the audit.'
            );
        }

        $recommended = $this->removeExactVersionSuffix(
            $currentTitle,
            $currentVersion
        );

        if (
            $recommended === $currentTitle
            || $recommended === ''
            || $recommended !== $expectedRecommended
        ) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Exact version-suffix safety check no longer matches the saved audit.'
            );
        }

        $updated = ($this->call)(
            'wp_update_post',
            [
                'ID' => $productId,
                'post_title' => $recommended,
                'post_name' => $slug,
            ],
            true
        );

        if (
            is_object($updated)
            && is_a($updated, 'WP_Error')
        ) {
            return $this->result(
                $productId,
                'ERROR',
                $currentTitle,
                $currentTitle,
                $slug,
                'WordPress rejected the title update.'
            );
        }

        if ((int) $updated !== $productId) {
            throw new RuntimeException(
                'Unexpected wp_update_post result for product #'
                . $productId
                . '.'
            );
        }

        $verified = ($this->call)('get_post', $productId);

        if (! is_object($verified)) {
            throw new RuntimeException(
                'Product #' . $productId
                . ' could not be verified after title update.'
            );
        }

        $verifiedTitle = trim((string) ($verified->post_title ?? ''));
        $verifiedSlug = trim((string) ($verified->post_name ?? ''));

        if (
            $verifiedTitle !== $recommended
            || $verifiedSlug !== $slug
        ) {
            throw new RuntimeException(
                'Post-write verification failed for product #'
                . $productId
                . '.'
            );
        }

        return $this->result(
            $productId,
            'UPDATED',
            $currentTitle,
            $recommended,
            $slug,
            'Exact stored version suffix removed; slug preserved.'
        );
    }

    private function removeExactVersionSuffix(
        string $title,
        string $version
    ): string {
        $title = trim($title);
        $version = trim($version);

        if ($title === '' || $version === '' || $version === '—') {
            return $title;
        }

        foreach ([' ' . $version, ' v' . $version] as $suffix) {
            if (
                strlen($title) > strlen($suffix)
                && str_ends_with($title, $suffix)
            ) {
                return trim(substr(
                    $title,
                    0,
                    -strlen($suffix)
                ));
            }
        }

        return $title;
    }

    /**
     * @return array{
     *   productId:int,
     *   status:string,
     *   oldTitle:string,
     *   newTitle:string,
     *   slug:string,
     *   reason:string
     * }
     */
    private function result(
        int $productId,
        string $status,
        string $oldTitle,
        string $newTitle,
        string $slug,
        string $reason
    ): array {
        return [
            'productId' => $productId,
            'status' => $status,
            'oldTitle' => $oldTitle,
            'newTitle' => $newTitle,
            'slug' => $slug,
            'reason' => $reason,
        ];
    }
}
