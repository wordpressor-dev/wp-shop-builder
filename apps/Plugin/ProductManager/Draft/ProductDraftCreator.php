<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Draft;

use Throwable;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;

final class ProductDraftCreator
{
    public function __construct(
        private readonly ProductDraftGatewayInterface $gateway,
        private readonly ProductDraftValidator $validator
    ) {
    }

    public function create(
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

        $slugOwner = $this->gateway->findBySlug(
            $data->slug
        );

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

        $skuOwner = $this->gateway->findBySku(
            $data->skuFilename
        );

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

        return new ProductDraftResult(
            true,
            $productId,
            [
                'DRAFT CREATED; ID ' . $productId,
                'TITLE = ' . $data->title(),
                'CORE PRODUCT = READY',
            ]
        );
    }
}
