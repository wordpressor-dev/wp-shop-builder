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
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;

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
        self::assertStringContainsString(
            '<h3>Основные возможности</h3>',
            $result->fields['long_description']
        );
        self::assertStringContainsString(
            '<h3>Кому подходит</h3>',
            $result->fields['long_description']
        );
        self::assertStringContainsString(
            '<h3>Совместимость и требования</h3>',
            $result->fields['long_description']
        );
        self::assertStringContainsString(
            '<h3>Что важно знать</h3>',
            $result->fields['long_description']
        );
        self::assertNotSame('', $result->fields['short_description']);
        self::assertNotSame('', $result->fields['meta_description']);
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
            'EDITORIAL CONTENT = AUTO-DRAFT V31.5 / REVIEW REQUIRED',
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

    public function testTemplateKitUsesEnvatoApiDescriptionForEditorialFacts(): void
    {
        $client = new class implements EnvatoClientInterface {
            public function fetch(
                string $itemUrl,
                string $token
            ): EnvatoItem {
                return new EnvatoItem(
                    53903194,
                    'Probiz – Business Consulting Elementor Template Kit',
                    'probiz',
                    '',
                    '2026-09-02',
                    'Rometheme',
                    'https://themeforest.net/item/probiz-business-consulting-elementor-template-kit/53903194',
                    4,
                    '2024-01-01T00:00:00+00:00',
                    [
                        'agency',
                        'business',
                        'consulting',
                        'corporate',
                        'finance',
                        'marketing',
                        'startup',
                    ],
                    'themeforest-53903194-probiz-business-consulting-elementor-template-kit.zip',
                    [
                        'id' => 53903194,
                        'name' => 'Probiz - Business Consulting Elementor Template Kit',
                        'author_username' => 'Rometheme',
                        'tags' => [
                            'agency',
                            'business',
                            'consulting',
                            'corporate',
                            'finance',
                            'marketing',
                            'startup',
                        ],
                        'description' => '<p>Probiz is an Elementor Template Kit for Business Consulting websites.</p>'
                            . '<p>Features :</p><ul>'
                            . '<li>Using Free Plugins (Elementor Pro is not required)</li>'
                            . '<li>True no-code customization with drag and drop</li>'
                            . '<li>100% Fully Responsive & mobile-friendly</li>'
                            . '<li>11+ pre-built templates ready to use</li>'
                            . '<li>Customize fonts and colors in one place (Global Theme Kit Style)</li>'
                            . '</ul><p>Templates in Zip :</p><ul>'
                            . '<li>Homepage</li><li>About Us</li><li>Services</li>'
                            . '<li>Pricing Plan</li><li>FAQs</li><li>Contact Us</li>'
                            . '<li>Header</li><li>Footer</li></ul>'
                            . '<p>Required Plugins :</p><ul>'
                            . '<li>Elementor</li><li>RomethemeForm</li>'
                            . '<li>RomethemeKit For Elementor</li></ul>'
                            . '<p>This kit has been optimized for use with the free Hello Elementor theme.</p>',
                    ],
                    ''
                );
            }
        };

        $controller = new ProductManagerController(
            $client,
            new ExistingTagSelector(
                new ProductManagerCatalogTagRepository()
            )
        );

        $result = $controller->autofill(
            'https://themeforest.net/item/probiz/53903194',
            'token'
        );

        self::assertTrue($result->success);
        self::assertStringContainsString(
            'более 11 готовых шаблонов',
            mb_strtolower(
                $result->fields['short_description'],
                'UTF-8'
            )
        );
        self::assertStringContainsString(
            'Elementor Pro не требуется',
            $result->fields['short_description']
        );
        self::assertStringContainsString(
            'RomethemeForm',
            $result->fields['long_description']
        );
        self::assertStringContainsString(
            'Hello Elementor',
            $result->fields['long_description']
        );
        self::assertContains(
            'EDITORIAL FACT SOURCE = ENVATO API DESCRIPTION',
            $result->logs
        );
        self::assertContains(
            'SALES PAGE EDITORIAL FACTS = READY',
            $result->logs
        );
        self::assertContains(
            'EDITORIAL CONTENT = AUTO-DRAFT V31.5 / REVIEW REQUIRED',
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

    public function resolveInBoth(
        string $name,
        string $slug
    ): ?CatalogTag {
        if (! $this->existsInBoth($name, $slug)) {
            return null;
        }

        return new CatalogTag(
            $slug === 'marketplace'
                ? 'торговая площадка'
                : 'elementor',
            $slug
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
