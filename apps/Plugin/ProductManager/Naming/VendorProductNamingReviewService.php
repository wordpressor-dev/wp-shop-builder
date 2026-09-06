<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Naming;

use Closure;

final class VendorProductNamingReviewService
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
     * @return list<VendorProductNamingReviewRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(10, $limit));
        $rows = array_slice(
            $this->reviews(),
            $offset,
            $limit
        );
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
    ): VendorProductNamingReviewRow {
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

        [
            $action,
            $confidence,
            $recommended,
            $reason,
        ] = $this->classify(
            $auditRow,
            $sales
        );

        return new VendorProductNamingReviewRow(
            $productId,
            $auditRow->currentTitle,
            $salesPage,
            $sales['status'],
            $sales['h1'],
            $sales['title'],
            $auditRow->headerName,
            $auditRow->productType,
            $translation->state,
            $translation->display(),
            $recommended,
            $action,
            $confidence,
            $reason
        );
    }

    /**
     * @param array{status:string,h1:string,title:string} $sales
     * @return array{string,string,string,string}
     */
    private function classify(
        VendorProductNamingAuditRow $auditRow,
        array $sales
    ): array {
        if ($this->packageProblem($auditRow)) {
            return [
                'PACKAGE_PROBLEM',
                'LOW',
                $auditRow->currentTitle,
                'Current ZIP/package evidence is incomplete, unavailable, suspicious, or type-inconsistent; fix package evidence before any naming decision.',
            ];
        }

        $current = $auditRow->currentTitle;
        $header = $auditRow->headerName;
        $candidate = $auditRow->recommendedTitle !== ''
            ? $auditRow->recommendedTitle
            : $header;
        $h1 = $sales['h1'];
        $pageTitle = $sales['title'];

        if ($h1 !== '') {
            if ($this->sameName($h1, $current)) {
                return [
                    'KEEP',
                    'HIGH',
                    $current,
                    'Official Vendor Sales Page H1 matches the current public title.',
                ];
            }

            if (
                $header !== ''
                && $this->sameName($h1, $header)
            ) {
                return [
                    'RENAME_CANDIDATE',
                    'HIGH',
                    $header,
                    'Official Vendor Sales Page H1 matches the current ZIP header name rather than the current public title.',
                ];
            }

            if (
                $candidate !== ''
                && $this->sameName($h1, $candidate)
            ) {
                return [
                    'RENAME_CANDIDATE',
                    'HIGH',
                    $candidate,
                    'Official Vendor Sales Page H1 matches the audit naming candidate.',
                ];
            }
        }

        if ($pageTitle !== '') {
            if ($this->containsName($pageTitle, $current)) {
                return [
                    'KEEP',
                    'MEDIUM',
                    $current,
                    'Official Vendor page title contains the current public title, but H1 did not provide an exact canonical-name match.',
                ];
            }

            if (
                $header !== ''
                && $this->containsName($pageTitle, $header)
            ) {
                return [
                    'RENAME_CANDIDATE',
                    'MEDIUM',
                    $header,
                    'Official Vendor page title contains the ZIP header name, but H1 did not provide an exact canonical-name match.',
                ];
            }

            if (
                $candidate !== ''
                && $this->containsName($pageTitle, $candidate)
            ) {
                return [
                    'RENAME_CANDIDATE',
                    'MEDIUM',
                    $candidate,
                    'Official Vendor page title contains the audit naming candidate.',
                ];
            }
        }

        return [
            'MANUAL_REVIEW',
            'MEDIUM',
            $current,
            $sales['status'] === 'OK'
                ? 'Vendor Sales Page was readable, but H1/title evidence does not unambiguously match the current title or ZIP naming candidate.'
                : 'Vendor Sales Page name evidence was unavailable or inconclusive; keep the current title until the official name is verified manually.',
        ];
    }

    private function packageProblem(
        VendorProductNamingAuditRow $row
    ): bool {
        if ($row->confidence === 'LOW') {
            return true;
        }

        foreach (
            [
                'no downloadable ZIP',
                'not available as a local uploads file',
                'could not be read',
                'internal package/component name',
                'product type differs',
            ]
            as $needle
        ) {
            if (str_contains($row->reason, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function sameName(string $left, string $right): bool
    {
        $left = $this->comparable($left);
        $right = $this->comparable($right);

        return $left !== '' && $left === $right;
    }

    private function containsName(
        string $container,
        string $name
    ): bool {
        $container = $this->comparable($container);
        $name = $this->comparable($name);

        return $container !== ''
            && $name !== ''
            && str_contains($container, $name);
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
        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return trim($value, " \t\n\r\0\x0B-|:");
    }
}
