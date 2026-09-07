<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\EnglishContentAuditPage;
use WPShop\App\Plugin\Admin\ElementorProTranslatePressPreflightPage;
use WPShop\App\Plugin\Admin\ProductBatchIntakePage;
use WPShop\App\Plugin\Admin\ProductEditorialMigrationPage;
use WPShop\App\Plugin\Admin\ProductManagerMenuOptimizer;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\Admin\ProductUpdateFullScannerPage;
use WPShop\App\Plugin\Admin\ProductUpdatePage;
use WPShop\App\Plugin\Admin\ProductUpdateQueuePage;
use WPShop\App\Plugin\Admin\ProductTitleVersionAuditPage;
use WPShop\App\Plugin\Admin\VendorProductNamingAuditPage;
use WPShop\App\Plugin\Admin\VendorCanonicalNamingMigrationPage;
use WPShop\App\Plugin\Admin\VendorCanonicalNamingMigrationV2Page;
use WPShop\App\Plugin\Admin\VendorCoverAuditPage;
use WPShop\App\Plugin\Admin\VendorCoverGeneratorPage;
use WPShop\App\Plugin\Admin\VendorProductNamingReviewPage;
use WPShop\App\Plugin\Admin\VendorProductNamingReviewV5Page;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\App\Plugin\ProductManager\Cover\OpenAIVendorAiImageGenerator;
use WPShop\App\Plugin\ProductManager\Cover\VendorAiCoverMediaService;
use WPShop\App\Plugin\ProductManager\Cover\VendorAiCoverPromptBuilder;
use WPShop\App\Plugin\ProductManager\Cover\VendorAiCoverService;
use WPShop\App\Plugin\ProductManager\Cover\Contracts\VendorAiImageGeneratorInterface;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverAuditService;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverPreviewService;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverRenderer;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemSearchResolver;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingAuditService;
use WPShop\App\Plugin\ProductManager\Naming\VendorCanonicalNamingMigrationService;
use WPShop\App\Plugin\ProductManager\Naming\VendorCanonicalNamingMigrationV2Service;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingMigrationService;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingReviewService;
use WPShop\App\Plugin\ProductManager\Naming\VendorProductNamingReviewV5Service;
use WPShop\App\Plugin\ProductManager\Naming\VendorSalesPageNameInspector;
use WPShop\App\Plugin\ProductManager\Naming\TranslatePressTitleInspector;
use WPShop\App\Plugin\ProductManager\Naming\ElementorProTranslatePressPreflightService;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionAuditService;
use WPShop\App\Plugin\ProductManager\Naming\ProductTitleVersionMigrationService;
use WPShop\App\Plugin\ProductManager\ProductManagerServiceProvider;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\EnglishContentAuditService;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressDictionary;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressRegistrar;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductBatchZipUpdateService;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateManualCandidateBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;
use WPShop\Core\Container\Container;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProviderTest extends TestCase
{
    public function testRegistersProductManagerServicesAndSubmenuPage(): void
    {
        $container = new Container();
        $registry = new AdminPageRegistry();
        $database = $this->createMock(
            DatabaseConnectionInterface::class
        );
        $container->set(AdminPageRegistry::class, $registry);
        $container->set(
            DatabaseConnectionInterface::class,
            $database
        );

        $provider = new ProductManagerServiceProvider($container);
        $provider->register();

        self::assertInstanceOf(
            EnvatoItemMapper::class,
            $container->get(EnvatoItemMapper::class)
        );
        self::assertInstanceOf(
            EnvatoItemSearchResolver::class,
            $container->get(EnvatoItemSearchResolver::class)
        );
        self::assertInstanceOf(
            WordPressCatalogTagRepository::class,
            $container->get(CatalogTagRepositoryInterface::class)
        );
        self::assertInstanceOf(
            ExistingTagSelector::class,
            $container->get(ExistingTagSelector::class)
        );
        self::assertInstanceOf(
            ExistingCatalogTagParser::class,
            $container->get(ExistingCatalogTagParser::class)
        );
        self::assertInstanceOf(
            WordPressFunctionCaller::class,
            $container->get(WordPressFunctionCaller::class)
        );
        self::assertInstanceOf(
            ProductEditorialMigrationService::class,
            $container->get(ProductEditorialMigrationService::class)
        );
        self::assertInstanceOf(
            ProductEditorialMigrationPage::class,
            $container->get(ProductEditorialMigrationPage::class)
        );
        self::assertInstanceOf(
            ProductBatchIntakeScanner::class,
            $container->get(ProductBatchIntakeScanner::class)
        );
        self::assertInstanceOf(
            ProductBatchIntakePage::class,
            $container->get(ProductBatchIntakePage::class)
        );
        self::assertInstanceOf(
            WordPressWooCommerceDraftGateway::class,
            $container->get(ProductDraftGatewayInterface::class)
        );
        self::assertInstanceOf(
            ProductDraftValidator::class,
            $container->get(ProductDraftValidator::class)
        );
        self::assertInstanceOf(
            ProductTaxonomyWriter::class,
            $container->get(ProductTaxonomyWriter::class)
        );
        self::assertInstanceOf(
            ProductMetadataWriter::class,
            $container->get(ProductMetadataWriter::class)
        );
        self::assertInstanceOf(
            SureRankWriter::class,
            $container->get(SureRankWriter::class)
        );
        self::assertInstanceOf(
            AdvancedLabelWriter::class,
            $container->get(AdvancedLabelWriter::class)
        );
        self::assertInstanceOf(
            ProductDraftCreator::class,
            $container->get(ProductDraftCreator::class)
        );
        self::assertInstanceOf(
            TranslationMapBuilder::class,
            $container->get(TranslationMapBuilder::class)
        );
        self::assertInstanceOf(
            TranslatePressDictionary::class,
            $container->get(TranslationDictionaryInterface::class)
        );
        self::assertInstanceOf(
            TranslatePressRegistrar::class,
            $container->get(TranslationRegistrarInterface::class)
        );
        self::assertInstanceOf(
            TranslatePressProductTranslator::class,
            $container->get(TranslatePressProductTranslator::class)
        );
        self::assertInstanceOf(
            EnglishContentAuditService::class,
            $container->get(EnglishContentAuditService::class)
        );
        self::assertInstanceOf(
            EnglishContentAuditPage::class,
            $container->get(EnglishContentAuditPage::class)
        );
        self::assertInstanceOf(
            OpenAIVendorAiImageGenerator::class,
            $container->get(VendorAiImageGeneratorInterface::class)
        );
        self::assertInstanceOf(
            OpenAIVendorAiImageGenerator::class,
            $container->get(OpenAIVendorAiImageGenerator::class)
        );
        self::assertInstanceOf(
            VendorAiCoverPromptBuilder::class,
            $container->get(VendorAiCoverPromptBuilder::class)
        );
        self::assertInstanceOf(
            VendorAiCoverMediaService::class,
            $container->get(VendorAiCoverMediaService::class)
        );
        self::assertInstanceOf(
            VendorAiCoverService::class,
            $container->get(VendorAiCoverService::class)
        );
        self::assertInstanceOf(
            ProductVersionUpdater::class,
            $container->get(ProductVersionUpdater::class)
        );
        self::assertInstanceOf(
            VendorProductNamingAuditService::class,
            $container->get(VendorProductNamingAuditService::class)
        );
        self::assertInstanceOf(
            VendorProductNamingMigrationService::class,
            $container->get(VendorProductNamingMigrationService::class)
        );
        self::assertInstanceOf(
            VendorProductNamingAuditPage::class,
            $container->get(VendorProductNamingAuditPage::class)
        );
        self::assertInstanceOf(
            VendorSalesPageNameInspector::class,
            $container->get(VendorSalesPageNameInspector::class)
        );
        self::assertInstanceOf(
            TranslatePressTitleInspector::class,
            $container->get(TranslatePressTitleInspector::class)
        );
        self::assertInstanceOf(
            VendorProductNamingReviewService::class,
            $container->get(VendorProductNamingReviewService::class)
        );
        self::assertInstanceOf(
            VendorProductNamingReviewPage::class,
            $container->get(VendorProductNamingReviewPage::class)
        );
        self::assertInstanceOf(
            VendorProductNamingReviewV5Service::class,
            $container->get(VendorProductNamingReviewV5Service::class)
        );
        self::assertInstanceOf(
            VendorProductNamingReviewV5Page::class,
            $container->get(VendorProductNamingReviewV5Page::class)
        );
        self::assertInstanceOf(
            VendorCanonicalNamingMigrationService::class,
            $container->get(VendorCanonicalNamingMigrationService::class)
        );
        self::assertInstanceOf(
            VendorCanonicalNamingMigrationPage::class,
            $container->get(VendorCanonicalNamingMigrationPage::class)
        );
        self::assertInstanceOf(
            VendorCanonicalNamingMigrationV2Service::class,
            $container->get(VendorCanonicalNamingMigrationV2Service::class)
        );
        self::assertInstanceOf(
            VendorCanonicalNamingMigrationV2Page::class,
            $container->get(VendorCanonicalNamingMigrationV2Page::class)
        );
        self::assertInstanceOf(
            ElementorProTranslatePressPreflightService::class,
            $container->get(ElementorProTranslatePressPreflightService::class)
        );
        self::assertInstanceOf(
            ElementorProTranslatePressPreflightPage::class,
            $container->get(ElementorProTranslatePressPreflightPage::class)
        );
        self::assertInstanceOf(
            VendorCoverAuditService::class,
            $container->get(VendorCoverAuditService::class)
        );
        self::assertInstanceOf(
            VendorCoverAuditPage::class,
            $container->get(VendorCoverAuditPage::class)
        );
        self::assertInstanceOf(
            VendorCoverRenderer::class,
            $container->get(VendorCoverRenderer::class)
        );
        self::assertInstanceOf(
            VendorCoverPreviewService::class,
            $container->get(VendorCoverPreviewService::class)
        );
        self::assertInstanceOf(
            VendorCoverGeneratorPage::class,
            $container->get(VendorCoverGeneratorPage::class)
        );
        self::assertInstanceOf(
            ProductTitleVersionAuditService::class,
            $container->get(ProductTitleVersionAuditService::class)
        );
        self::assertInstanceOf(
            ProductTitleVersionMigrationService::class,
            $container->get(ProductTitleVersionMigrationService::class)
        );
        self::assertInstanceOf(
            ProductTitleVersionAuditPage::class,
            $container->get(ProductTitleVersionAuditPage::class)
        );
        self::assertInstanceOf(
            ProductBatchZipUpdateService::class,
            $container->get(ProductBatchZipUpdateService::class)
        );
        self::assertInstanceOf(
            ProductUpdateEnvatoAdvisor::class,
            $container->get(ProductUpdateEnvatoAdvisor::class)
        );
        self::assertInstanceOf(
            ProductUpdateManualCandidateBuilder::class,
            $container->get(ProductUpdateManualCandidateBuilder::class)
        );
        self::assertInstanceOf(
            ProductManagerController::class,
            $container->get(ProductManagerController::class)
        );
        self::assertInstanceOf(
            ProductManagerMenuOptimizer::class,
            $container->get(ProductManagerMenuOptimizer::class)
        );
        self::assertSame(
            $container->get(ProductBatchIntakePage::class),
            $registry->submenus()[0]
        );
        self::assertSame(
            'wp-shop-builder-product-batch-intake',
            $registry->submenus()[0]->slug()
        );
        self::assertSame(
            $container->get(ProductUpdateQueuePage::class),
            $registry->submenus()[1]
        );
        self::assertSame(
            'wp-shop-builder-product-update-queue',
            $registry->submenus()[1]->slug()
        );
        self::assertSame(
            $container->get(ProductUpdateFullScannerPage::class),
            $registry->submenus()[2]
        );
        self::assertSame(
            'wp-shop-builder-product-update-full-scan',
            $registry->submenus()[2]->slug()
        );
        self::assertSame(
            $container->get(ProductManagerPage::class),
            $registry->submenus()[3]
        );
        self::assertSame(
            'wp-shop-builder-product-manager',
            $registry->submenus()[3]->slug()
        );
        self::assertSame(
            $container->get(ProductEditorialMigrationPage::class),
            $registry->submenus()[4]
        );
        self::assertSame(
            'wp-shop-builder-product-editorial-migration',
            $registry->submenus()[4]->slug()
        );
        self::assertSame(
            $container->get(EnglishContentAuditPage::class),
            $registry->submenus()[5]
        );
        self::assertSame(
            'wp-shop-builder-en-content-audit',
            $registry->submenus()[5]->slug()
        );
        self::assertSame(
            $container->get(ProductUpdatePage::class),
            $registry->submenus()[6]
        );
        self::assertSame(
            'wp-shop-builder-product-update',
            $registry->submenus()[6]->slug()
        );
        self::assertSame(
            $container->get(VendorProductNamingAuditPage::class),
            $registry->submenus()[8]
        );
        self::assertSame(
            'wp-shop-builder-vendor-naming-audit',
            $registry->submenus()[8]->slug()
        );
        self::assertSame(
            $container->get(ProductTitleVersionAuditPage::class),
            $registry->submenus()[9]
        );
        self::assertSame(
            'wp-shop-builder-title-version-audit',
            $registry->submenus()[9]->slug()
        );
        self::assertSame(
            $container->get(VendorProductNamingReviewPage::class),
            $registry->submenus()[10]
        );
        self::assertSame(
            'wp-shop-builder-vendor-naming-review-v4',
            $registry->submenus()[10]->slug()
        );
        self::assertSame(
            $container->get(VendorProductNamingReviewV5Page::class),
            $registry->submenus()[11]
        );
        self::assertSame(
            'wp-shop-builder-vendor-naming-review-v5',
            $registry->submenus()[11]->slug()
        );
        self::assertSame(
            $container->get(VendorCanonicalNamingMigrationPage::class),
            $registry->submenus()[12]
        );
        self::assertSame(
            'wp-shop-builder-vendor-canonical-naming-migration',
            $registry->submenus()[12]->slug()
        );
        self::assertSame(
            $container->get(VendorCanonicalNamingMigrationV2Page::class),
            $registry->submenus()[13]
        );
        self::assertSame(
            'wp-shop-builder-vendor-canonical-naming-migration-v2',
            $registry->submenus()[13]->slug()
        );
        self::assertSame(
            $container->get(ElementorProTranslatePressPreflightPage::class),
            $registry->submenus()[14]
        );
        self::assertSame(
            'wp-shop-builder-elementor-pro-trp-preflight',
            $registry->submenus()[14]->slug()
        );
        self::assertSame(
            $container->get(VendorCoverAuditPage::class),
            $registry->submenus()[15]
        );
        self::assertSame(
            'wp-shop-builder-vendor-cover-audit',
            $registry->submenus()[15]->slug()
        );
        self::assertSame(
            $container->get(VendorCoverGeneratorPage::class),
            $registry->submenus()[16]
        );
        self::assertSame(
            'wp-shop-builder-vendor-cover-generator',
            $registry->submenus()[16]->slug()
        );
    }
}
