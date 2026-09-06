<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use RuntimeException;

final class VendorProductNamingMigrationService
{
    public const SAFE_REASON =
        'Current Vendor title is the verified ZIP product name followed by a dash-separated descriptive marketing suffix.';

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorProductNamingAuditService $audit,
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
        $expectedRecommended = trim(
            (string) ($expected['recommendedTitle'] ?? '')
        );

        if (! $this->eligibleSnapshotRow($expected)) {
            return $this->result(
                $productId,
                'SKIP',
                $expectedTitle,
                $expectedTitle,
                '',
                'Saved audit row is not an eligible safe descriptive-suffix RENAME.'
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

        if ($currentTitle !== $expectedTitle) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Current title changed after the Vendor Naming Audit.'
            );
        }

        $current = $this->audit->auditCurrentVendorProduct($productId);

        if ($current === null) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Product is no longer classified as Vendor; marketplace/source protection stopped the write.'
            );
        }

        if (
            $current->action !== 'RENAME'
            || $current->confidence !== 'HIGH'
            || $current->reason !== self::SAFE_REASON
            || $current->currentTitle !== $expectedTitle
            || $current->recommendedTitle !== $expectedRecommended
        ) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Current ZIP/title evidence no longer matches the safe migration snapshot.'
            );
        }

        if (! $this->onlyRemovesDashSuffix(
            $currentTitle,
            $expectedRecommended
        )) {
            return $this->result(
                $productId,
                'SKIP',
                $currentTitle,
                $currentTitle,
                $slug,
                'Recommended title would change more than a dash-separated descriptive suffix.'
            );
        }

        $updated = ($this->call)(
            'wp_update_post',
            [
                'ID' => $productId,
                'post_title' => $expectedRecommended,
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
                'WordPress rejected the Vendor title update.'
            );
        }

        if ((int) $updated !== $productId) {
            throw new RuntimeException(
                'Unexpected wp_update_post result for Vendor product #'
                . $productId
                . '.'
            );
        }

        $verified = ($this->call)('get_post', $productId);

        if (! is_object($verified)) {
            throw new RuntimeException(
                'Vendor product #' . $productId
                . ' could not be verified after title update.'
            );
        }

        $verifiedTitle = trim((string) ($verified->post_title ?? ''));
        $verifiedSlug = trim((string) ($verified->post_name ?? ''));

        if (
            $verifiedTitle !== $expectedRecommended
            || $verifiedSlug !== $slug
        ) {
            throw new RuntimeException(
                'Post-write verification failed for Vendor product #'
                . $productId
                . '.'
            );
        }

        return $this->result(
            $productId,
            'UPDATED',
            $currentTitle,
            $expectedRecommended,
            $slug,
            'Verified Vendor ZIP name preserved; only dash-separated descriptive suffix removed.'
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function eligibleSnapshotRow(array $row): bool
    {
        return (int) ($row['productId'] ?? 0) > 0
            && (string) ($row['action'] ?? '') === 'RENAME'
            && (string) ($row['confidence'] ?? '') === 'HIGH'
            && (string) ($row['reason'] ?? '') === self::SAFE_REASON
            && trim((string) ($row['currentTitle'] ?? '')) !== ''
            && trim((string) ($row['recommendedTitle'] ?? '')) !== '';
    }

    private function onlyRemovesDashSuffix(
        string $currentTitle,
        string $recommendedTitle
    ): bool {
        $currentTitle = trim($currentTitle);
        $recommendedTitle = trim($recommendedTitle);

        if (
            $currentTitle === ''
            || $recommendedTitle === ''
            || $currentTitle === $recommendedTitle
        ) {
            return false;
        }

        foreach ([' – ', ' — ', ' - '] as $separator) {
            $prefix = $recommendedTitle . $separator;

            if (! str_starts_with($currentTitle, $prefix)) {
                continue;
            }

            return strlen(trim(substr(
                $currentTitle,
                strlen($prefix)
            ))) >= 4;
        }

        return false;
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
