<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\ProductSourceType;

final class VendorProductNamingAuditService
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly ProductArchiveIdentityInspector $identityInspector
    ) {
    }

    public function candidateCount(): int
    {
        return count($this->vendorProductIds());
    }

    /**
     * @return list<VendorProductNamingAuditRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(50, $limit));
        $ids = array_slice(
            $this->vendorProductIds(),
            $offset,
            $limit
        );
        $rows = [];

        foreach ($ids as $productId) {
            $rows[] = $this->auditProduct($productId);
        }

        return $rows;
    }

    private function auditProduct(int $productId): VendorProductNamingAuditRow
    {
        $currentTitle = trim((string) ($this->call)(
            'get_post_field',
            'post_title',
            $productId
        ));
        $version = $this->storedVersion($productId);
        $currentBaseTitle = $this->baseTitle(
            $currentTitle,
            $version
        );
        $storedProductType = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_product_type',
            true
        ));
        $downloadUrl = $this->downloadUrl($productId);
        $archivePath = $this->localArchivePath($downloadUrl);

        if ($archivePath === '') {
            return new VendorProductNamingAuditRow(
                $productId,
                $currentTitle,
                $currentBaseTitle,
                '',
                $currentBaseTitle,
                $storedProductType,
                'REVIEW',
                'LOW',
                $downloadUrl === ''
                    ? 'DOWNLOAD_FILE_MISSING'
                    : 'ARCHIVE_NOT_LOCAL',
                $downloadUrl === ''
                    ? 'Vendor product has no downloadable ZIP to verify the canonical product name.'
                    : 'Vendor ZIP is not available as a local uploads file; canonical name was not verified.'
            );
        }

        $identity = $this->identityInspector->inspect(
            $archivePath,
            basename($archivePath)
        );

        if (! $identity->success || trim($identity->name) === '') {
            return new VendorProductNamingAuditRow(
                $productId,
                $currentTitle,
                $currentBaseTitle,
                '',
                $currentBaseTitle,
                $storedProductType,
                'REVIEW',
                'LOW',
                'ZIP_IDENTITY_UNAVAILABLE',
                'Plugin Name / Theme Name could not be read from the current Vendor ZIP.'
            );
        }

        $headerName = $this->normalizeName($identity->name);
        [$recommendedTitle, $marketingTail] = $this->recommendedFromHeader(
            $headerName
        );
        $productType = trim($identity->productType) !== ''
            ? trim($identity->productType)
            : $storedProductType;
        $evidence = $productType === 'theme'
            ? 'ZIP_THEME_NAME'
            : ($productType === 'plugin'
                ? 'ZIP_PLUGIN_NAME'
                : 'ZIP_HEADER_NAME');

        if ($marketingTail !== '') {
            return new VendorProductNamingAuditRow(
                $productId,
                $currentTitle,
                $currentBaseTitle,
                $headerName,
                $recommendedTitle,
                $productType,
                'REVIEW',
                'MEDIUM',
                $evidence,
                'ZIP header contains a likely marketing tagline after the product name; review the shortened recommendation before any rename.'
            );
        }

        if ($recommendedTitle === '') {
            return new VendorProductNamingAuditRow(
                $productId,
                $currentTitle,
                $currentBaseTitle,
                $headerName,
                $currentBaseTitle,
                $productType,
                'REVIEW',
                'LOW',
                $evidence,
                'ZIP header name is present but could not produce a safe canonical recommendation.'
            );
        }

        if ($currentTitle === $recommendedTitle) {
            return new VendorProductNamingAuditRow(
                $productId,
                $currentTitle,
                $currentBaseTitle,
                $headerName,
                $recommendedTitle,
                $productType,
                'KEEP',
                'HIGH',
                $evidence,
                'Published title already matches the canonical Plugin Name / Theme Name from the Vendor ZIP.'
            );
        }

        return new VendorProductNamingAuditRow(
            $productId,
            $currentTitle,
            $currentBaseTitle,
            $headerName,
            $recommendedTitle,
            $productType,
            'RENAME',
            'HIGH',
            $evidence,
            $currentBaseTitle === $recommendedTitle
                ? 'Current title contains a version or other suffix; canonical Vendor ZIP name is unchanged.'
                : 'Published title differs from the canonical Plugin Name / Theme Name in the Vendor ZIP.'
        );
    }

    /**
     * @return list<int>
     */
    private function vendorProductIds(): array
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

        $vendorIds = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0 || ! $this->isVendorProduct($productId)) {
                continue;
            }

            $vendorIds[] = $productId;
        }

        return $vendorIds;
    }

    private function isVendorProduct(int $productId): bool
    {
        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $storedSource = strtolower(trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            '_wp_shop_source_type',
            true
        )));

        /*
         * Marketplace protection wins over every legacy/meta fallback.
         * ThemeForest, CodeCanyon and every Envato host are never audited.
         */
        if ($this->isMarketplaceSalesPage($salesPage)) {
            return false;
        }

        if ($storedSource === ProductSourceType::ENVATO) {
            return false;
        }

        if ($storedSource === ProductSourceType::VENDOR) {
            return true;
        }

        /*
         * Legacy direct-Vendor products may predate _wp_shop_source_type.
         * Include only products with a non-empty non-marketplace Sales Page.
         */
        return $storedSource === '' && $salesPage !== '';
    }

    private function isMarketplaceSalesPage(string $salesPage): bool
    {
        $host = parse_url(trim($salesPage), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return false;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        foreach (['themeforest.net', 'codecanyon.net', 'envato.com'] as $domain) {
            if (
                $host === $domain
                || str_ends_with($host, '.' . $domain)
            ) {
                return true;
            }
        }

        return false;
    }

    private function storedVersion(int $productId): string
    {
        $version = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'attr_version_value',
            true
        ));

        return $version === '—' ? '' : $version;
    }

    private function baseTitle(string $title, string $version): string
    {
        $title = trim($title);
        $version = trim($version);

        if ($title === '' || $version === '') {
            return $title;
        }

        foreach ([' ' . $version, ' v' . $version] as $suffix) {
            if (str_ends_with($title, $suffix)) {
                return trim(substr($title, 0, -strlen($suffix)));
            }
        }

        return $title;
    }

    private function downloadUrl(int $productId): string
    {
        $files = ($this->call)(
            'get_post_meta',
            $productId,
            '_downloadable_files',
            true
        );

        if (! is_array($files) || $files === []) {
            return '';
        }

        $first = reset($files);

        if (! is_array($first)) {
            return '';
        }

        $file = $first['file'] ?? '';

        return is_string($file) ? trim($file) : '';
    }

    private function localArchivePath(string $downloadUrl): string
    {
        if ($downloadUrl === '') {
            return '';
        }

        if (
            is_file($downloadUrl)
            && strtolower((string) pathinfo($downloadUrl, PATHINFO_EXTENSION)) === 'zip'
        ) {
            return $downloadUrl;
        }

        $uploads = ($this->call)('wp_upload_dir');

        if (! is_array($uploads)) {
            return '';
        }

        $baseDir = rtrim(trim((string) ($uploads['basedir'] ?? '')), '/\\');
        $baseUrl = rtrim(trim((string) ($uploads['baseurl'] ?? '')), '/');

        if ($baseDir === '' || $baseUrl === '') {
            return '';
        }

        $downloadHost = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $downloadPath = (string) parse_url($downloadUrl, PHP_URL_PATH);
        $basePath = rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        if (
            $downloadHost === ''
            || $baseHost === ''
            || $downloadHost !== $baseHost
            || $downloadPath === ''
        ) {
            return '';
        }

        $prefix = $basePath . '/';

        if (! str_starts_with($downloadPath, $prefix)) {
            return '';
        }

        $relative = rawurldecode(ltrim(
            substr($downloadPath, strlen($prefix)),
            '/'
        ));

        if (
            $relative === ''
            || str_contains($relative, '../')
            || str_contains($relative, '..\\')
        ) {
            return '';
        }

        $path = $baseDir
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (
            ! is_file($path)
            || strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            return '';
        }

        return $path;
    }

    private function normalizeName(string $name): string
    {
        $name = html_entity_decode(
            trim($name),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $name = preg_replace('/\s+/u', ' ', $name);

        return is_string($name) ? trim($name) : '';
    }

    /**
     * @return array{string,string}
     */
    private function recommendedFromHeader(string $headerName): array
    {
        if ($headerName === '') {
            return ['', ''];
        }

        $parts = preg_split(
            '/\s+(?:[|–—]|-{1})\s+/u',
            $headerName,
            2
        );

        if (
            is_array($parts)
            && count($parts) === 2
            && trim((string) $parts[0]) !== ''
            && mb_strlen(trim((string) $parts[1])) >= 10
        ) {
            return [
                trim((string) $parts[0]),
                trim((string) $parts[1]),
            ];
        }

        return [$headerName, ''];
    }
}
