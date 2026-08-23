<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftWriterInterface;
use WPShop\App\Plugin\ProductManager\Draft\ExistingProduct;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;

final class ProductDraftCreatorTest extends TestCase
{
    public function testCreatesValidatedDraft(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->createdProductId = 5028;
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->create($this->validData());

        self::assertTrue($result->success);
        self::assertSame(5028, $result->productId);
        self::assertSame(1, $gateway->createCalls);
        self::assertContains(
            'DRAFT CREATED; ID 5028',
            $result->logs
        );
        self::assertContains(
            'FINALIZATION = READY',
            $result->logs
        );
    }

    public function testRunsPostCreateWritersInOrderAndAppendsLogs(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->createdProductId = 5028;
        $recorder = new ProductDraftWriterRecorder();
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator(),
            [
                new ProductDraftCreatorWriter(
                    'TAXONOMY = READY',
                    $recorder,
                    'taxonomy'
                ),
                new ProductDraftCreatorWriter(
                    'META = READY',
                    $recorder,
                    'meta'
                ),
            ]
        );

        $result = $creator->create($this->validData());

        self::assertTrue($result->success);
        self::assertSame(
            ['taxonomy', 'meta'],
            $recorder->calls
        );
        self::assertContains(
            'TAXONOMY = READY',
            $result->logs
        );
        self::assertContains(
            'META = READY',
            $result->logs
        );
    }

    public function testKeepsCreatedDraftWhenFinalizationFails(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->createdProductId = 5028;
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator(),
            [new ProductDraftFailingWriter()]
        );

        $result = $creator->create($this->validData());

        self::assertFalse($result->success);
        self::assertSame(5028, $result->productId);
        self::assertContains(
            'DRAFT CREATED BUT FINALIZATION FAILED.',
            $result->logs
        );
        self::assertContains(
            'ERROR MESSAGE: Metadata write failed.',
            $result->logs
        );
        self::assertContains(
            'ACTION: keep Draft for repair; do not publish yet.',
            $result->logs
        );
    }

    public function testStopsBeforeGatewayWhenValidationFails(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->create(
            $this->validData(shortDescription: '')
        );

        self::assertFalse($result->success);
        self::assertSame(0, $gateway->createCalls);
        self::assertContains(
            'RU Short Description is required.',
            $result->logs
        );
    }

    public function testStopsOnExistingProductSlug(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->slugOwner = new ExistingProduct(
            100,
            'publish'
        );
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->create($this->validData());

        self::assertFalse($result->success);
        self::assertSame(0, $gateway->createCalls);
        self::assertContains(
            'PRODUCT SLUG ALREADY EXISTS: aabbe',
            $result->logs
        );
    }

    public function testExplainsSkuConflictInTrash(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->skuOwner = new ExistingProduct(
            5027,
            'trash'
        );
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->create($this->validData());

        self::assertFalse($result->success);
        self::assertContains(
            'EXISTING STATUS: trash',
            $result->logs
        );
        self::assertContains(
            'ACTION: permanently delete the old test product from Trash or clear its SKU.',
            $result->logs
        );
    }

    public function testConvertsGatewayExceptionIntoCreateLog(): void
    {
        $gateway = new ProductDraftCreatorGateway();
        $gateway->createException = new RuntimeException(
            'WooCommerce save failed.'
        );
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator()
        );

        $result = $creator->create($this->validData());

        self::assertFalse($result->success);
        self::assertContains(
            'ERROR MESSAGE: WooCommerce save failed.',
            $result->logs
        );
    }

    private function validData(
        string $shortDescription = 'RU short'
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
            'RU long',
            'RU meta',
            'EN short',
            'EN long',
            'EN meta',
            'Pre-activated.',
            false,
            false
        );
    }
}

final class ProductDraftCreatorGateway implements
    ProductDraftGatewayInterface
{
    public ?ExistingProduct $slugOwner = null;
    public ?ExistingProduct $skuOwner = null;
    public int $createdProductId = 1;
    public int $createCalls = 0;
    public ?RuntimeException $createException = null;

    public function findBySlug(
        string $slug
    ): ?ExistingProduct {
        return $this->slugOwner;
    }

    public function findBySku(
        string $sku
    ): ?ExistingProduct {
        return $this->skuOwner;
    }

    public function createCore(
        ProductDraftData $data
    ): int {
        $this->createCalls++;

        if ($this->createException instanceof RuntimeException) {
            throw $this->createException;
        }

        return $this->createdProductId;
    }
}

final class ProductDraftWriterRecorder
{
    /** @var list<string> */
    public array $calls = [];
}

final class ProductDraftCreatorWriter implements
    ProductDraftWriterInterface
{
    public function __construct(
        private readonly string $log,
        private readonly ProductDraftWriterRecorder $recorder,
        private readonly string $name
    ) {
    }

    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        $this->recorder->calls[] = $this->name;

        return [$this->log];
    }
}

final class ProductDraftFailingWriter implements
    ProductDraftWriterInterface
{
    public function write(
        int $productId,
        ProductDraftData $data
    ): array {
        throw new RuntimeException(
            'Metadata write failed.'
        );
    }
}
