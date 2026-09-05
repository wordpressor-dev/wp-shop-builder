<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ExistingProduct;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;

final class ProductManagerControllerTest extends TestCase
{
    public function testAutofillsMappedEnvatoFieldsAndExistingTags(): void
    {
        $controller = new ProductManagerController(
            new ProductManagerEnvatoClient(),
            new ExistingTagSelector(
                new ProductManagerCatalogTagRepository()
            )
        );

        $result = $controller->autofill(
            ' https://themeforest.net/item/aabbe/26350912 ',
            ' token '
        );

        self::assertTrue($result->success);
        self::assertSame(
            'Aabbe – Digital Marketplace WordPress Theme',
            $result->fields['base_title']
        );
        self::assertSame('aabbe', $result->fields['slug']);
        self::assertSame('26350912', $result->fields['item_id']);
        self::assertSame('6.2.0', $result->fields['version']);
        self::assertSame(
            '2025-04-20',
            $result->fields['source_update_date']
        );
        self::assertSame(
            "elementor|elementor\nторговая площадка|marketplace",
            $result->fields['tags']
        );
        self::assertSame(
            'https://assets.market.envato.com/aabbe-landscape.jpg',
            $result->fields['featured_image_source_url']
        );
        self::assertSame('', $result->fields['featured_image_id']);
        self::assertContains(
            'ENVATO AUTOFILL = READY',
            $result->logs
        );
        self::assertContains(
            'FEATURED IMAGE SOURCE = ENVATO PREVIEW READY',
            $result->logs
        );
        self::assertContains(
            'FEATURED IMAGE AUTO-IMPORT = UNAVAILABLE',
            $result->logs
        );
        self::assertContains(
            'FEATURED IMAGE FALLBACK = MANUAL PICKER',
            $result->logs
        );
    }

    public function testVendorPreflightDoesNotRequireEnvatoItemId(): void
    {
        $controller = new ProductManagerController(
            new ProductManagerEnvatoClient(),
            new ExistingTagSelector(
                new ProductManagerCatalogTagRepository()
            ),
            new ProductDraftCreator(
                new ProductManagerDraftGateway(),
                new ProductDraftValidator()
            )
        );

        $result = $controller->preflightDraft(
            new ProductDraftData(
                'WP All Import Pro',
                'wp-all-import-pro',
                0,
                '5.1.0',
                '2026-09-05',
                'Soflyy',
                '249',
                'https://www.wpallimport.com/',
                'wp-all-import-pro-5.1.0.zip',
                'https://wp-shop.org/wp-content/uploads/'
                    . 'woocommerce_uploads/PLUGINS/Vendor/'
                    . 'soflyy/wp-all-import-pro/'
                    . 'wp-all-import-pro-5.1.0.zip',
                0,
                [],
                'RU short',
                'RU long',
                'RU meta',
                'EN short',
                'EN long',
                'EN meta',
                '',
                false,
                false,
                true,
                'plugin'
            )
        );

        self::assertTrue($result->success);
        self::assertContains(
            'SOURCE TYPE = VENDOR',
            $result->logs
        );
        self::assertContains(
            'VENDOR SKU / VERSION = MATCH',
            $result->logs
        );
        self::assertNotContains(
            'Envato Item ID must be positive before SKU generation.',
            $result->logs
        );
    }

    public function testReturnsSafeLogWhenEnvatoFails(): void
    {
        $client = $this->createMock(
            EnvatoClientInterface::class
        );
        $client->method('fetch')->willThrowException(
            new RuntimeException('Envato unavailable.')
        );
        $repository = $this->createMock(
            CatalogTagRepositoryInterface::class
        );
        $controller = new ProductManagerController(
            $client,
            new ExistingTagSelector($repository)
        );

        $result = $controller->autofill('url', 'token');

        self::assertFalse($result->success);
        self::assertSame([], $result->fields);
        self::assertContains(
            'ERROR MESSAGE: Envato unavailable.',
            $result->logs
        );
    }
}

final class ProductManagerEnvatoClient implements
    EnvatoClientInterface
{
    public function fetch(
        string $itemUrl,
        string $token
    ): EnvatoItem {
        TestCase::assertSame(
            'https://themeforest.net/item/aabbe/26350912',
            $itemUrl
        );
        TestCase::assertSame('token', $token);

        return new EnvatoItem(
            26350912,
            'Aabbe – Digital Marketplace WordPress Theme',
            'aabbe',
            '6.2.0',
            '2025-04-20',
            'QuomodoTheme',
            'https://themeforest.net/item/aabbe/26350912',
            100,
            '2020-04-20T00:00:00+00:00',
            ['elementor', 'marketplace', 'unknown-envato-tag'],
            'themeforest-26350912-aabbe-6.2.0.zip',
            [
                'name' => 'Aabbe Digital Marketplace',
                'tags' => [
                    'elementor',
                    'marketplace',
                    'unknown-envato-tag',
                ],
            ],
            'https://assets.market.envato.com/aabbe-landscape.jpg'
        );
    }
}

final class ProductManagerCatalogTagRepository implements
    CatalogTagRepositoryInterface
{
    public function existsInBoth(
        string $name,
        string $slug
    ): bool {
        return in_array(
            $slug,
            ['elementor', 'marketplace'],
            true
        );
    }
}


final class ProductManagerDraftGateway implements
    ProductDraftGatewayInterface
{
    public function findBySlug(string $slug): ?ExistingProduct
    {
        return null;
    }

    public function findBySku(string $sku): ?ExistingProduct
    {
        return null;
    }

    public function createCore(ProductDraftData $data): int
    {
        return 9001;
    }
}
