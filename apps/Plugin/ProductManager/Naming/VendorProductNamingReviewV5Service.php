<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;

final class VendorProductNamingReviewV5Service
{
    /** @var list<VendorProductNamingAuditRow>|null */
    private ?array $reviewRows = null;

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly VendorProductNamingAuditService $audit,
        private readonly VendorSalesPageNameInspector $salesPageInspector,
        private readonly TranslatePressTitleInspector $translationInspector,
        private readonly Closure $call
    ) {
    }

    public function candidateCount(): int
    {
        return count($this->reviews());
    }

    /**
     * @return list<VendorProductNamingReviewV5Row>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(10, $limit));
        $rows = array_slice($this->reviews(), $offset, $limit);
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->review($row);
        }

        return $result;
    }

    /**
     * @return list<VendorProductNamingAuditRow>
     */
    private function reviews(): array
    {
        if ($this->reviewRows !== null) {
            return $this->reviewRows;
        }

        $rows = [];
        $total = $this->audit->candidateCount();

        for ($offset = 0; $offset < $total; $offset += 50) {
            foreach ($this->audit->scan($offset, 50) as $row) {
                if ($row->action === 'REVIEW') {
                    $rows[] = $row;
                }
            }
        }

        $this->reviewRows = $rows;

        return $rows;
    }

    private function review(
        VendorProductNamingAuditRow $auditRow
    ): VendorProductNamingReviewV5Row {
        $productId = $auditRow->productId;
        $salesPage = trim((string) ($this->call)(
            'get_post_meta',
            $productId,
            'sales_page',
            true
        ));
        $sales = $this->salesPageInspector->inspect($salesPage);
        $translation = $this->translationInspector->inspect(
            $auditRow->currentTitle
        );
        $packageStatus = $this->packageStatus($auditRow);
        [
            $action,
            $confidence,
            $recommended,
            $reason,
        ] = $this->classifyNaming($auditRow, $sales);

        return new VendorProductNamingReviewV5Row(
            $productId,
            $auditRow->currentTitle,
            $salesPage,
            $sales['status'],
            $sales['h1'],
            $sales['title'],
            $auditRow->headerName,
            $auditRow->productType,
            $packageStatus,
            $translation->state,
            $translation->display(),
            $this->translationSafety($translation->state),
            $recommended,
            $action,
            $confidence,
            $reason
        );
    }

    private function packageStatus(
        VendorProductNamingAuditRow $row
    ): string {
        $reason = $row->reason;

        if (str_contains($reason, 'no downloadable ZIP')) {
            return 'ZIP_MISSING';
        }

        if (str_contains(
            $reason,
            'not available as a local uploads file'
        )) {
            return 'ZIP_NOT_LOCAL';
        }

        if (str_contains($reason, 'could not be read')) {
            return 'ZIP_IDENTITY_UNAVAILABLE';
        }

        if (str_contains(
            $reason,
            'internal package/component name'
        )) {
            return 'SUSPICIOUS_ZIP_HEADER';
        }

        if (str_contains($reason, 'product type differs')) {
            return 'TYPE_MISMATCH';
        }

        if (str_contains(
            $reason,
            'contains an obvious marketing tagline'
        )) {
            return 'ZIP_MARKETING_HEADER';
        }

        return 'OK';
    }

    private function translationSafety(string $state): string
    {
        return match ($state) {
            'TRANSLATED' => 'TRP_REMAP_REQUIRED',
            'MULTIPLE_TRANSLATIONS' => 'TRP_CONFLICT_REVIEW',
            'UNFINISHED' => 'TRP_FINISH_REQUIRED',
            'NOT_FOUND' => 'TRP_NOT_FOUND',
            'TRP_TABLE_MISSING' => 'TRP_TABLE_MISSING',
            default => 'TRP_REVIEW',
        };
    }

    /**
     * @param array{status:string,h1:string,title:string} $sales
     * @return array{string,string,string,string}
     */
    private function classifyNaming(
        VendorProductNamingAuditRow $auditRow,
        array $sales
    ): array {
        $current = $auditRow->currentTitle;
        $header = $auditRow->headerName;
        $h1 = $sales['h1'];
        $pageTitle = $sales['title'];

        if ($this->sameName($h1, $current)) {
            return [
                'KEEP',
                'HIGH',
                $current,
                'Official Vendor Sales Page H1 matches the current public title.',
            ];
        }

        if ($this->titleStartsWithName($pageTitle, $current)) {
            return [
                'KEEP',
                'HIGH',
                $current,
                'Official Vendor page title starts with the current public title as a complete name segment.',
            ];
        }

        $core = $this->currentCoreCandidate($current);

        if ($core !== $current) {
            if ($this->sameName($h1, $core)) {
                return [
                    'RENAME_CANDIDATE',
                    'HIGH',
                    $core,
                    'Official Vendor Sales Page H1 matches the current title after removing only an explanatory suffix.',
                ];
            }

            if ($this->titleStartsWithName($pageTitle, $core)) {
                return [
                    'RENAME_CANDIDATE',
                    'MEDIUM',
                    $core,
                    'Official Vendor page title starts with the current title core after removing only an explanatory suffix.',
                ];
            }
        }

        if ($header !== '') {
            if ($this->sameName($h1, $header)) {
                return [
                    'RENAME_CANDIDATE',
                    'HIGH',
                    $header,
                    'Official Vendor Sales Page H1 exactly matches the ZIP header name.',
                ];
            }

            if ($this->titleStartsWithName($pageTitle, $header)) {
                return [
                    'RENAME_CANDIDATE',
                    'MEDIUM',
                    $header,
                    'Official Vendor page title starts with the ZIP header as a complete name segment.',
                ];
            }
        }

        return [
            'MANUAL_REVIEW',
            'MEDIUM',
            $current,
            $sales['status'] === 'OK'
                ? 'Sales Page evidence is readable but does not identify one canonical name with enough certainty.'
                : 'Sales Page evidence is unavailable; keep current H1 until the official product name is verified.',
        ];
    }

    private function currentCoreCandidate(string $current): string
    {
        $current = trim($current);
        $withoutIncluding = preg_replace(
            '/\s*\((?:including|includes)\b[^)]*\)\s*$/iu',
            '',
            $current
        );

        if (is_string($withoutIncluding)) {
            $current = trim($withoutIncluding);
        }

        if (preg_match(
            '/^(.+?)\s+[–—-]\s+.+$/u',
            $current,
            $matches
        ) === 1) {
            $core = trim((string) $matches[1]);

            if ($core !== '') {
                return $core;
            }
        }

        return $current;
    }

    private function sameName(string $left, string $right): bool
    {
        $left = $this->comparable($left);
        $right = $this->comparable($right);

        return $left !== '' && $left === $right;
    }

    private function titleStartsWithName(
        string $pageTitle,
        string $name
    ): bool {
        $pageTitle = $this->comparable($pageTitle);
        $name = $this->comparable($name);

        if ($pageTitle === '' || $name === '') {
            return false;
        }

        if ($pageTitle === $name) {
            return true;
        }

        if (! str_starts_with($pageTitle, $name)) {
            return false;
        }

        $rest = substr($pageTitle, strlen($name));

        return preg_match(
            '/^\s*(?:-|\||•|:)\s*/u',
            $rest
        ) === 1;
    }

    private function comparable(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = strtolower(trim($value));
        $value = str_replace(['–', '—'], '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B-|:");
    }
}
