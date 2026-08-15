<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ExistingProduct;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;

final class ProductDraftPreflightTest extends TestCase
{
    public function testPreflightChecksAvailabilityWithoutCreatingProduct(): void
    {
        $gateway = new ProductDraftPreflightGateway();
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->preflight($this->validData());

        self::assertTrue($result->success);
        self::assertNull($result->productId);
        self::assertSame(0, $gateway->createCalls);
        self::assertContains('PREFLIGHT = READY', $result->logs);
        self::assertContains('NO PRODUCT WRITTEN = YES', $result->logs);
        self::assertContains(
            'SLUG = AVAILABLE: aabbe',
            $result->logs
        );
        self::assertContains(
            'SKU = AVAILABLE: themeforest-26350912-aabbe-6.2.0.zip',
            $result->logs
        );
    }

    public function testPreflightReportsExistingSlugWithoutCreatingProduct(): void
    {
        $gateway = new ProductDraftPreflightGateway();
        $gateway->slugOwner = new ExistingProduct(5028, 'publish');
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->preflight($this->validData());

        self::assertFalse($result->success);
        self::assertNull($result->productId);
        self::assertSame(0, $gateway->createCalls);
        self::assertContains(
            'PREFLIGHT = REVIEW REQUIRED',
            $result->logs
        );
        self::assertContains(
            'PRODUCT SLUG ALREADY EXISTS: aabbe',
            $result->logs
        );
        self::assertContains(
            'SLUG OWNER PRODUCT ID: 5028',
            $result->logs
        );
    }

    public function testReportsEditorialValidationAndDuplicateIdentityTogether(): void
    {
        $gateway = new ProductDraftPreflightGateway();
        $gateway->slugOwner = new ExistingProduct(5028, 'publish');
        $gateway->skuOwner = new ExistingProduct(5028, 'publish');
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->preflight(
            $this->validData(
                shortDescription: '',
                longDescription: '',
                metaDescription: ''
            )
        );

        self::assertFalse($result->success);
        self::assertNull($result->productId);
        self::assertSame(0, $gateway->createCalls);
        self::assertContains(
            'NO PRODUCT WRITTEN = YES',
            $result->logs
        );
        self::assertContains(
            'RU Short Description is required.',
            $result->logs
        );
        self::assertContains(
            'RU Long Description is required.',
            $result->logs
        );
        self::assertContains(
            'SureRank Meta Description is required.',
            $result->logs
        );
        self::assertContains(
            'PRODUCT SLUG ALREADY EXISTS: aabbe',
            $result->logs
        );
        self::assertContains(
            'SLUG OWNER PRODUCT ID: 5028',
            $result->logs
        );
        self::assertContains(
            'SKU ALREADY EXISTS: themeforest-26350912-aabbe-6.2.0.zip',
            $result->logs
        );
        self::assertContains(
            'SKU OWNER PRODUCT ID: 5028',
            $result->logs
        );
    }

    private function validData(
        string $shortDescription = 'RU short',
        string $longDescription = 'RU long',
        string $metaDescription = 'RU meta'
    ): ProductDraftData {
        return new ProductDraftData(
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            26350912,
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            '249',
            'https://themeforest.net/item/aabbe/26350912',
            'themeforest-26350912-aabbe-6.2.0.zip',
            '',
            0,
            [],
            $shortDescription,
            $longDescription,
            $metaDescription,
            'EN short',
            'EN long',
            'EN meta',
            'Pre-activated.',
            false,
            false
        );
    }
}

final class ProductDraftPreflightGateway implements
    ProductDraftGatewayInterface
{
    public ?ExistingProduct $slugOwner = null;
    public ?ExistingProduct $skuOwner = null;
    public int $createCalls = 0;

    public function findBySlug(string $slug): ?ExistingProduct
    {
        return $this->slugOwner;
    }

    public function findBySku(string $sku): ?ExistingProduct
    {
        return $this->skuOwner;
    }

    public function createCore(ProductDraftData $data): int
    {
        $this->createCalls++;

        return 9999;
    }
}
