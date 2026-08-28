<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductUpdateScanner
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly ProductVersionUpdater $updater,
        private readonly ProductUpdateEnvatoAdvisor $advisor,
        private readonly Closure $call
    ) {
    }

    /**
     * @return list<ProductUpdateScanRow>
     */
    public function scan(
        int $offset,
        int $limit,
        string $token
    ): array {
        $offset = max(0, $offset);
        $limit = max(1, min(25, $limit));
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => 'sales_page',
                        'value' => 'themeforest.net/item/',
                        'compare' => 'LIKE',
                    ],
                ],
                'suppress_filters' => true,
            ]
        );

        if (! is_array($ids)) {
            return [];
        }

        $rows = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0) {
                continue;
            }

            try {
                $snapshot = $this->updater->load($productId);
            } catch (Throwable $exception) {
                $rows[] = new ProductUpdateScanRow(
                    $productId,
                    '[unavailable]',
                    '',
                    '',
                    '',
                    'LOAD_FAILED',
                    $exception->getMessage()
                );
                continue;
            }

            if (trim($token) === '') {
                $rows[] = new ProductUpdateScanRow(
                    $snapshot->productId,
                    $snapshot->title,
                    $snapshot->version,
                    '',
                    '',
                    'TOKEN_MISSING',
                    'Envato token is required for update comparison.'
                );
                continue;
            }

            try {
                $suggestion = $this->advisor->suggest(
                    $snapshot,
                    $token
                );
            } catch (Throwable $exception) {
                $rows[] = new ProductUpdateScanRow(
                    $snapshot->productId,
                    $snapshot->title,
                    $snapshot->version,
                    '',
                    '',
                    'MANUAL_REVIEW',
                    $exception->getMessage()
                );
                continue;
            }

            $envatoVersion = trim($suggestion->version);
            $envatoUpdateDate = trim($suggestion->updateDate);
            $status = 'MANUAL_REVIEW';
            $message = 'Envato version is empty; verify the public changelog.';
            $productType = CatalogProductType::infer(
                $snapshot->baseTitle,
                $snapshot->salesPage
            );

            if ($envatoVersion !== '') {
                if ($snapshot->version === $envatoVersion) {
                    $status = 'SAME';
                    $message = 'Current product matches Envato metadata.';
                } elseif (
                    $snapshot->version === ''
                    || version_compare(
                        $envatoVersion,
                        $snapshot->version,
                        '>'
                    )
                ) {
                    $status = 'UPDATE_AVAILABLE';
                    $message = 'Verify the public changelog before updating.';
                }
            } elseif ($productType === CatalogProductType::TEMPLATE_KIT) {
                $currentDate = trim($snapshot->sourceUpdateDate);

                if (
                    $envatoUpdateDate !== ''
                    && $currentDate !== ''
                    && $envatoUpdateDate <= $currentDate
                ) {
                    $status = 'SAME';
                    $message = 'Template Kit has no published version; ThemeForest update date has not advanced.';
                } elseif (
                    $envatoUpdateDate !== ''
                    && $currentDate !== ''
                    && $envatoUpdateDate > $currentDate
                ) {
                    $status = 'MANUAL_REVIEW';
                    $message = 'Template Kit has no published version; ThemeForest update date advanced. Verify the downloaded package manually.';
                } else {
                    $status = 'MANUAL_REVIEW';
                    $message = 'Template Kit has no published version and update-date comparison is incomplete.';
                }
            }

            $rows[] = new ProductUpdateScanRow(
                $snapshot->productId,
                $snapshot->title,
                $snapshot->version,
                $envatoVersion,
                $envatoUpdateDate,
                $status,
                $message
            );
        }

        return $rows;
    }
}
