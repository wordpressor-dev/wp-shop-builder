<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;
use WPShop\App\Plugin\ProductManager\ProductSourceType;

final class ProductBatchCreateAllService
{
    public const MAX_BATCH = 10;

    /**
     * @param Closure(string, string, string, string, string, string): ProductDraftResult $createDraft
     * @param Closure(string, string, string): string $moveToReview
     */
    public function __construct(
        private readonly Closure $createDraft,
        private readonly Closure $moveToReview
    ) {
    }

    /**
     * @param list<array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }> $rows
     * @param array<string, string> $references
     * @param array<string, string> $notes
     * @param array<string, string> $sourceTypes
     * @return array{
     *   entries: list<array{filename:string,reference:string,notes:string,sourceType:string}>,
     *   missing: list<string>
     * }
     */
    public function prepare(
        array $rows,
        array $references,
        array $notes = [],
        array $sourceTypes = []
    ): array {
        $entries = [];
        $missing = [];

        foreach ($rows as $row) {
            if (
                $row['productId'] > 0
                || $row['productType'] === ''
            ) {
                continue;
            }

            $filename = trim($row['filename']);

            if ($filename === '') {
                continue;
            }

            $reference = $row['itemId'] > 0
                ? (string) $row['itemId']
                : trim((string) ($references[$filename] ?? ''));
            $sourceType = $row['itemId'] > 0
                ? ProductSourceType::ENVATO
                : strtolower(
                    trim(
                        (string) (
                            $sourceTypes[$filename]
                            ?? ProductSourceType::ENVATO
                        )
                    )
                );

            if (
                ! in_array(
                    $sourceType,
                    [
                        ProductSourceType::ENVATO,
                        ProductSourceType::VENDOR,
                    ],
                    true
                )
            ) {
                $sourceType = ProductSourceType::ENVATO;
            }

            if (
                $sourceType === ProductSourceType::ENVATO
                && $reference === ''
            ) {
                $missing[] = $filename;

                continue;
            }

            $entries[] = [
                'filename' => $filename,
                'reference' => $reference,
                'notes' => trim((string) ($notes[$filename] ?? '')),
                'sourceType' => $sourceType,
            ];
        }

        return [
            'entries' => $entries,
            'missing' => $missing,
        ];
    }

    /**
     * @param list<array{filename:string,reference:string,notes:string,sourceType:string}> $entries
     * @return array{
     *   processed:int,
     *   created:int,
     *   failed:int,
     *   productIds:list<int>,
     *   remaining:list<array{filename:string,reference:string,notes:string,sourceType:string}>,
     *   continue:bool,
     *   logs:list<string>
     * }
     */
    public function process(
        string $uploadsBaseDir,
        string $folder,
        array $entries,
        int $limit = self::MAX_BATCH
    ): array {
        $limit = max(1, min(self::MAX_BATCH, $limit));
        $batch = array_slice($entries, 0, $limit);
        $remaining = array_slice($entries, count($batch));
        $processed = 0;
        $created = 0;
        $failed = 0;
        $productIds = [];
        $logs = [
            'AUTO CREATE DRAFTS = RECEIVED',
            'BATCH LIMIT = ' . $limit,
            'PENDING BEFORE = ' . count($entries),
        ];

        foreach ($batch as $entry) {
            ++$processed;
            $filename = $entry['filename'];
            $reference = $entry['reference'];
            $notes = $entry['notes'];
            $sourceType = $entry['sourceType'];
            $logs[] = str_repeat('=', 72);
            $logs[] = 'AUTO NEW ITEM = ' . $filename;
            $logs[] = 'SOURCE TYPE = ' . strtoupper($sourceType);
            $logs[] = $reference !== ''
                ? 'SOURCE REFERENCE = ' . $reference
                : 'SOURCE REFERENCE = ZIP HEADER';

            try {
                $result = ($this->createDraft)(
                    $uploadsBaseDir,
                    $folder,
                    $filename,
                    $reference,
                    $notes,
                    $sourceType
                );
            } catch (Throwable $exception) {
                $result = new ProductDraftResult(
                    false,
                    null,
                    [
                        'BATCH CREATE EXCEPTION = '
                            . $exception->getMessage(),
                    ]
                );
            }

            foreach ($result->logs as $line) {
                $logs[] = $line;
            }

            if ($result->success && $result->productId !== null) {
                ++$created;
                $productIds[] = $result->productId;
                $logs[] = 'AUTO NEW ITEM RESULT = DRAFT CREATED';

                continue;
            }

            ++$failed;
            $logs[] = 'AUTO NEW ITEM RESULT = REVIEW';

            try {
                $target = ($this->moveToReview)(
                    $uploadsBaseDir,
                    $folder,
                    $filename
                );
                $logs[] = 'FAILED NEW ZIP MOVED TO = ' . $target;
            } catch (Throwable $exception) {
                $logs[] = 'FAILED NEW ZIP MOVE TO REVIEW = FAILED';
                $logs[] = 'FAILED NEW ZIP MOVE ERROR = '
                    . $exception->getMessage();
            }
        }

        $logs[] = str_repeat('=', 72);
        $logs[] = 'AUTO CREATE PROCESSED = ' . $processed;
        $logs[] = 'AUTO CREATE DRAFTED = ' . $created;
        $logs[] = 'AUTO CREATE REVIEW = ' . $failed;
        $logs[] = 'AUTO CREATE REMAINING = ' . count($remaining);
        $logs[] = $remaining !== []
            ? 'AUTO CREATE CONTINUE = REQUIRED'
            : 'AUTO CREATE DRAFTS = COMPLETE';

        return [
            'processed' => $processed,
            'created' => $created,
            'failed' => $failed,
            'productIds' => $productIds,
            'remaining' => $remaining,
            'continue' => $remaining !== [],
            'logs' => $logs,
        ];
    }
}
