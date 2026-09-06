<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;

final class ProductTitleVersionAuditService
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function candidateCount(): int
    {
        return count($this->publishedProductIds());
    }

    /**
     * @return list<ProductTitleVersionAuditRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));
        $ids = array_slice(
            $this->publishedProductIds(),
            $offset,
            $limit
        );
        $rows = [];

        foreach ($ids as $productId) {
            $rows[] = $this->auditProduct($productId);
        }

        return $rows;
    }

    private function auditProduct(int $productId): ProductTitleVersionAuditRow
    {
        $title = trim((string) ($this->call)(
            'get_post_field',
            'post_title',
            $productId
        ));
        $version = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'attr_version_value',
            true
        ));
        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $source = $this->source($salesPage);

        if ($version === '' || $version === '—') {
            return new ProductTitleVersionAuditRow(
                $productId,
                $title,
                $version,
                $title,
                $source,
                'KEEP',
                'No stored version is available for exact title-suffix comparison.'
            );
        }

        $recommended = $this->removeExactVersionSuffix(
            $title,
            $version
        );

        if ($recommended === $title) {
            return new ProductTitleVersionAuditRow(
                $productId,
                $title,
                $version,
                $title,
                $source,
                'KEEP',
                'Current title does not end with the exact stored version.'
            );
        }

        if ($recommended === '') {
            return new ProductTitleVersionAuditRow(
                $productId,
                $title,
                $version,
                $title,
                $source,
                'REVIEW',
                'Exact version suffix matched, but removing it would leave an empty title.'
            );
        }

        return new ProductTitleVersionAuditRow(
            $productId,
            $title,
            $version,
            $recommended,
            $source,
            'REMOVE_VERSION',
            'Current title ends with the exact stored version; only that suffix can be removed safely.'
        );
    }

    /**
     * @return list<int>
     */
    private function publishedProductIds(): array
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

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $result[] = $id;
            }
        }

        return $result;
    }

    private function removeExactVersionSuffix(
        string $title,
        string $version
    ): string {
        $title = trim($title);
        $version = trim($version);

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

    private function source(string $salesPage): string
    {
        $host = parse_url(trim($salesPage), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return 'UNKNOWN';
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (
            $host === 'themeforest.net'
            || str_ends_with($host, '.themeforest.net')
        ) {
            return 'THEMEFOREST';
        }

        if (
            $host === 'codecanyon.net'
            || str_ends_with($host, '.codecanyon.net')
        ) {
            return 'CODECANYON';
        }

        if (
            $host === 'envato.com'
            || str_ends_with($host, '.envato.com')
        ) {
            return 'ENVATO';
        }

        return 'VENDOR';
    }
}
