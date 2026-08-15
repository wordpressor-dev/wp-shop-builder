<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use Throwable;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;

final class ProductDraftCreator
{
    /**
     * @param list<ProductDraftWriterInterface> $writers
     */
    public function __construct(
        private readonly ProductDraftGatewayInterface $gateway,
        private readonly ProductDraftValidator $validator,
        private readonly array $writers = []
    ) {
    }

    public function preflight(
        ProductDraftData $data
    ): ProductDraftResult {
        $issues = $this->validator->validate($data);
        $logs = [
            'NO PRODUCT WRITTEN = YES',
        ];

        try {
            $slugOwner = trim($data->slug) !== ''
                ? $this->gateway->findBySlug($data->slug)
                : null;
            $skuOwner = trim($data->skuFilename) !== ''
                ? $this->gateway->findBySku($data->skuFilename)
                : null;
        } catch (Throwable $exception) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'NO PRODUCT WRITTEN = YES',
                    'STOP: DUPLICATE CHECK FAILED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        if ($slugOwner instanceof ExistingProduct) {
            $issues[] = 'PRODUCT SLUG ALREADY EXISTS: '
                . $data->slug;
            $issues[] = 'SLUG OWNER PRODUCT ID: '
                . $slugOwner->id;
            $issues[] = 'SLUG OWNER STATUS: '
                . $slugOwner->status;
        } elseif (trim($data->slug) !== '') {
            $logs[] = 'SLUG = AVAILABLE: ' . $data->slug;
        }

        if ($skuOwner instanceof ExistingProduct) {
            $issues[] = 'SKU ALREADY EXISTS: '
                . $data->skuFilename;
            $issues[] = 'SKU OWNER PRODUCT ID: '
                . $skuOwner->id;
            $issues[] = 'SKU OWNER STATUS: '
                . $skuOwner->status;

            if ($skuOwner->status === 'trash') {
                $issues[] = 'ACTION: permanently delete the old test product from Trash or clear its SKU.';
            }
        } elseif (trim($data->skuFilename) !== '') {
            $logs[] = 'SKU = AVAILABLE: ' . $data->skuFilename;
        }

        if ($issues !== []) {
            return new ProductDraftResult(
                false,
                null,
                array_merge(
                    $logs,
                    ['PREFLIGHT = REVIEW REQUIRED'],
                    $issues
                )
            );
        }

        return new ProductDraftResult(
            true,
            null,
            array_merge(
                $logs,
                [
                    'PREFLIGHT = READY',
                    'TITLE = ' . $data->title(),
                ]
            )
        );
    }

    public function create(
        ProductDraftData $data
    ): ProductDraftResult {
        $check = $this->checkBeforeCreate($data);

        if (! $check->success) {
            return $check;
        }

        try {
            $productId = $this->gateway->createCore($data);
        } catch (Throwable $exception) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'STOP: DRAFT CREATE FAILED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        if ($productId <= 0) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'STOP: DRAFT CREATE FAILED.',
                    'INVALID PRODUCT ID RETURNED.',
                ]
            );
        }

        $logs = [
            'DRAFT CREATED; ID ' . $productId,
            'TITLE = ' . $data->title(),
            'CORE PRODUCT = READY',
        ];

        foreach ($this->writers as $writer) {
            try {
                $logs = array_merge(
                    $logs,
                    $writer->write(
                        $productId,
                        $data
                    )
                );
            } catch (Throwable $exception) {
                return new ProductDraftResult(
                    false,
                    $productId,
                    array_merge(
                        $logs,
                        [
                            'DRAFT CREATED BUT FINALIZATION FAILED.',
                            'WRITER = ' . $writer::class,
                            'ERROR TYPE: ' . $exception::class,
                            'ERROR MESSAGE: ' . $exception->getMessage(),
                            'ACTION: keep Draft for repair; do not publish yet.',
                        ]
                    )
                );
            }
        }

        $logs[] = 'FINALIZATION = READY';

        return new ProductDraftResult(
            true,
            $productId,
            $logs
        );
    }

    private function checkBeforeCreate(
        ProductDraftData $data
    ): ProductDraftResult {
        $errors = $this->validator->validate($data);

        if ($errors !== []) {
            return new ProductDraftResult(
                false,
                null,
                array_merge(
                    ['STOP: DRAFT NOT CREATED.'],
                    $errors
                )
            );
        }

        try {
            $slugOwner = $this->gateway->findBySlug(
                $data->slug
            );
            $skuOwner = $this->gateway->findBySku(
                $data->skuFilename
            );
        } catch (Throwable $exception) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'STOP: DUPLICATE CHECK FAILED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        if ($slugOwner instanceof ExistingProduct) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'STOP: DRAFT NOT CREATED.',
                    'PRODUCT SLUG ALREADY EXISTS: ' . $data->slug,
                    'EXISTING PRODUCT ID: ' . $slugOwner->id,
                    'EXISTING STATUS: ' . $slugOwner->status,
                ]
            );
        }

        if ($skuOwner instanceof ExistingProduct) {
            return new ProductDraftResult(
                false,
                null,
                [
                    'STOP: DRAFT NOT CREATED.',
                    'SKU ALREADY EXISTS: ' . $data->skuFilename,
                    'EXISTING PRODUCT ID: ' . $skuOwner->id,
                    'EXISTING STATUS: ' . $skuOwner->status,
                    $skuOwner->status === 'trash'
                        ? 'ACTION: permanently delete the old test product from Trash or clear its SKU.'
                        : 'ACTION: review the existing product before creating a duplicate.',
                ]
            );
        }

        return new ProductDraftResult(
            true,
            null,
            ['PRE-CREATE CHECKS = READY']
        );
    }
}
