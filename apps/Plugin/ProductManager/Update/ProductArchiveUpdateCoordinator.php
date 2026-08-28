<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;

final readonly class ProductArchiveUpdateCoordinator
{
    public function __construct(
        private ProductVersionUpdater $updater,
        private ProductArchiveUploader $archiveUploader,
        private ?ProductArchiveVersionInspector $archiveInspector = null
    ) {
    }

    /**
     * @param array<string, mixed> $archiveFile
     */
    public function update(
        ProductUpdateData $data,
        array $archiveFile = []
    ): ProductUpdateResult {
        $preparedData = $data;
        $inspectionLogs = [];

        if ($this->archiveSupplied($archiveFile)) {
            $productType = CatalogProductType::infer(
                $data->baseTitle,
                $data->salesPage
            );
            $inspector = $this->archiveInspector
                ?? new ProductArchiveVersionInspector();
            $inspection = $inspector->inspect(
                $archiveFile,
                $productType
            );
            $inspectionLogs = $inspection->logs;

            if (! $inspection->success) {
                return new ProductUpdateResult(
                    false,
                    array_merge(
                        ['UPDATE REQUEST = RECEIVED'],
                        $inspectionLogs,
                        ['STOP: PRODUCT NOT UPDATED.']
                    )
                );
            }

            $preparedData = $preparedData->withVersion(
                $productType === CatalogProductType::TEMPLATE_KIT
                    ? ''
                    : $inspection->version
            );
        }

        $archiveResult = $this->archiveUploader->storeForUpdate(
            $archiveFile,
            $preparedData->baseTitle,
            $preparedData->salesPage,
            $preparedData->itemId,
            $preparedData->version
        );

        if (! $archiveResult->success) {
            return new ProductUpdateResult(
                false,
                array_merge(
                    ['UPDATE REQUEST = RECEIVED'],
                    $inspectionLogs,
                    $archiveResult->logs,
                    ['STOP: PRODUCT NOT UPDATED.']
                )
            );
        }

        if ($archiveResult->supplied) {
            $preparedData = $preparedData->withArchive(
                $archiveResult->skuFilename,
                $archiveResult->downloadUrl
            );
        }

        $preflight = $this->updater->preflight($preparedData);

        if (! $preflight->success) {
            $rollbackLogs = $archiveResult->supplied
                ? $this->archiveUploader->rollback($archiveResult)
                : [];

            return new ProductUpdateResult(
                false,
                array_merge(
                    $inspectionLogs,
                    $archiveResult->logs,
                    $preflight->logs,
                    ['ONE-CLICK PREFLIGHT = STOPPED UPDATE'],
                    $rollbackLogs
                )
            );
        }

        $result = $this->updater->update($preparedData);
        $finishLogs = [];

        if ($archiveResult->supplied) {
            $finishLogs = $result->success
                ? $this->archiveUploader->finalize($archiveResult)
                : $this->archiveUploader->rollback($archiveResult);
        }

        return new ProductUpdateResult(
            $result->success,
            array_merge(
                $inspectionLogs,
                $archiveResult->logs,
                $preflight->logs,
                $result->logs,
                $finishLogs,
                $archiveResult->supplied && $result->success
                    ? ['ONE-CLICK ZIP UPDATE = READY']
                    : []
            )
        );
    }

    /**
     * @param array<string, mixed> $archiveFile
     */
    private function archiveSupplied(array $archiveFile): bool
    {
        if ($archiveFile === []) {
            return false;
        }

        return (int) ($archiveFile['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE;
    }
}
