<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;

final readonly class ProductArchiveUpdateCoordinator
{
    public function __construct(
        private ProductVersionUpdater $updater,
        private ProductArchiveUploader $archiveUploader
    ) {
    }

    /**
     * @param array<string, mixed> $archiveFile
     */
    public function update(
        ProductUpdateData $data,
        array $archiveFile = []
    ): ProductUpdateResult {
        $archiveResult = $this->archiveUploader->storeForUpdate(
            $archiveFile,
            $data->baseTitle,
            $data->salesPage,
            $data->itemId,
            $data->version
        );

        if (! $archiveResult->success) {
            return new ProductUpdateResult(
                false,
                array_merge(
                    ['UPDATE REQUEST = RECEIVED'],
                    $archiveResult->logs,
                    ['STOP: PRODUCT NOT UPDATED.']
                )
            );
        }

        $preparedData = $data;

        if ($archiveResult->supplied) {
            $preparedData = $data->withArchive(
                $archiveResult->skuFilename,
                $archiveResult->downloadUrl
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
                $archiveResult->logs,
                $result->logs,
                $finishLogs
            )
        );
    }
}
