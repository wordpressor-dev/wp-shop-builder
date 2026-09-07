<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

use LogicException;
use WPShop\App\Plugin\Admin\EnglishContentAuditPage;
use WPShop\App\Plugin\Admin\ElementorProTranslatePressPreflightPage;
use WPShop\App\Plugin\Admin\ProductBatchIntakePage;
use WPShop\App\Plugin\Admin\ProductEditorialMigrationPage;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\Admin\ProductUpdateFullScannerPage;
use WPShop\App\Plugin\Admin\ProductUpdatePage;
use WPShop\App\Plugin\Admin\ProductUpdateQueuePage;
use WPShop\App\Plugin\Admin\ProductUpdateQueueReturnNavigation;
use WPShop\App\Plugin\Admin\ProductUpdateScannerPage;
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
use WPShop\App\Plugin\ProductManager\Batch\ProductArchiveIdentityInspector;
use WPShop\App\Plugin\ProductManager\Batch\ProductBatchIntakeScanner;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverAuditService;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverPreviewService;
use WPShop\App\Plugin\ProductManager\Cover\VendorCoverRenderer;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Editorial\ProductEditorialMigrationService;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemSearchResolver;
use WPShop\App\Plugin\ProductManager\Envato\WordPressEnvatoTransport;
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
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\EnglishContentAuditService;
use WPShop\App\Plugin\ProductManager\Translation\PreparedEnglishProductContent;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressDictionary;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressRegistrar;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveUpdateCoordinator;
use WPShop\App\Plugin\ProductManager\Update\ProductBatchZipUpdateService;
use WPShop\App\Plugin\ProductManager\Update\ProductArchiveVersionInspector;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateManualCandidateBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanner;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $registry = $this->container->get(AdminPageRegistry::class);
        $database = $this->container->get(DatabaseConnectionInterface::class);

        if (! $registry instanceof AdminPageRegistry) {
            throw new LogicException(
                'AdminPageRegistry must be registered before Product Manager.'
            );
        }

        if (! $database instanceof DatabaseConnectionInterface) {
            throw new LogicException(
                'DatabaseConnection must be registered before Product Manager.'
            );
        }

        $mapper = new EnvatoItemMapper();
        $transport = new WordPressEnvatoTransport();
        $envatoClient = new EnvatoClient($transport(...), $mapper);
        $envatoSearchResolver = new EnvatoItemSearchResolver(
            $transport(...)
        );
        $tagRepository = new WordPressCatalogTagRepository();
        $tagSelector = new ExistingTagSelector($tagRepository);
        $tagParser = new ExistingCatalogTagParser($tagRepository);
        $functionCaller = new WordPressFunctionCaller();
        $preparedEnglishContent = new PreparedEnglishProductContent(
            $functionCaller(...)
        );
        $archiveUploader = new ProductArchiveUploader($functionCaller(...));
        $archiveVersionInspector = new ProductArchiveVersionInspector();
        $archiveIdentityInspector = new ProductArchiveIdentityInspector();
        $batchIntakeScanner = new ProductBatchIntakeScanner(
            $functionCaller(...),
            $archiveVersionInspector,
            $archiveIdentityInspector,
            $envatoSearchResolver
        );
        $batchIntakePage = new ProductBatchIntakePage(
            $batchIntakeScanner,
            $functionCaller(...)
        );
        $draftGateway = new WordPressWooCommerceDraftGateway(
            $functionCaller(...)
        );
        $draftValidator = new ProductDraftValidator();
        $taxonomyWriter = new ProductTaxonomyWriter($functionCaller(...));
        $metadataWriter = new ProductMetadataWriter($functionCaller(...));
        $sureRankWriter = new SureRankWriter($functionCaller(...));
        $labelWriter = new AdvancedLabelWriter($functionCaller(...));
        $draftCreator = new ProductDraftCreator(
            $draftGateway,
            $draftValidator,
            [
                $taxonomyWriter,
                $metadataWriter,
                $sureRankWriter,
                $labelWriter,
            ]
        );
        $translationMapBuilder = new TranslationMapBuilder();
        $translationDictionary = new TranslatePressDictionary(
            $database,
            $functionCaller(...),
            $translationMapBuilder
        );
        $translationRegistrar = new TranslatePressRegistrar(
            $functionCaller(...)
        );
        $translator = new TranslatePressProductTranslator(
            $translationMapBuilder,
            $translationDictionary,
            $translationRegistrar,
            $functionCaller(...)
        );
        $englishContentAudit = new EnglishContentAuditService(
            $functionCaller(...),
            $database
        );
        $englishContentAuditPage = new EnglishContentAuditPage(
            $englishContentAudit,
            $functionCaller(...)
        );
        $editorialMigrationService = new ProductEditorialMigrationService(
            $functionCaller(...),
            $envatoClient,
            translate: $translator->translate(...)
        );
        $editorialMigrationPage = new ProductEditorialMigrationPage(
            $editorialMigrationService,
            $functionCaller(...)
        );
        $controller = new ProductManagerController(
            $envatoClient,
            $tagSelector,
            $draftCreator,
            $translator,
            $tagParser,
            $archiveUploader
        );
        $page = new ProductManagerPage($controller, $functionCaller(...));
        $versionUpdater = new ProductVersionUpdater($functionCaller(...));
        $archiveUpdateCoordinator = new ProductArchiveUpdateCoordinator(
            $versionUpdater,
            $archiveUploader
        );
        $batchZipUpdateService = new ProductBatchZipUpdateService(
            $versionUpdater,
            $archiveUpdateCoordinator
        );
        $updateAdvisor = new ProductUpdateEnvatoAdvisor($envatoClient);
        $manualCandidateBuilder = new ProductUpdateManualCandidateBuilder();
        $updatePage = new ProductUpdatePage(
            $versionUpdater,
            $updateAdvisor,
            $manualCandidateBuilder,
            $functionCaller(...),
            $archiveUpdateCoordinator
        );
        $updateScanner = new ProductUpdateScanner(
            $versionUpdater,
            $updateAdvisor,
            $functionCaller(...)
        );
        $updateScannerPage = new ProductUpdateScannerPage(
            $updateScanner,
            $functionCaller(...)
        );
        $updateFullScannerPage = new ProductUpdateFullScannerPage(
            $updateScanner,
            $functionCaller(...)
        );
        $updateQueuePage = new ProductUpdateQueuePage(
            $functionCaller(...),
            $batchZipUpdateService
        );
        $updateQueueReturnNavigation = new ProductUpdateQueueReturnNavigation(
            $functionCaller(...)
        );
        $vendorNamingAudit = new VendorProductNamingAuditService(
            $functionCaller(...),
            $archiveIdentityInspector
        );
        $vendorNamingMigration = new VendorProductNamingMigrationService(
            $vendorNamingAudit,
            $functionCaller(...)
        );
        $vendorNamingAuditPage = new VendorProductNamingAuditPage(
            $vendorNamingAudit,
            $vendorNamingMigration,
            $functionCaller(...)
        );
        $vendorSalesPageNameInspector = new VendorSalesPageNameInspector(
            $functionCaller(...)
        );
        $translatePressTitleInspector = new TranslatePressTitleInspector(
            $database
        );
        $vendorNamingReview = new VendorProductNamingReviewService(
            $vendorNamingAudit,
            $vendorSalesPageNameInspector,
            $translatePressTitleInspector,
            $functionCaller(...)
        );
        $vendorNamingReviewPage = new VendorProductNamingReviewPage(
            $vendorNamingReview,
            $functionCaller(...)
        );
        $vendorNamingReviewV5 = new VendorProductNamingReviewV5Service(
            $vendorNamingAudit,
            $vendorSalesPageNameInspector,
            $translatePressTitleInspector,
            $functionCaller(...)
        );
        $vendorNamingReviewV5Page = new VendorProductNamingReviewV5Page(
            $vendorNamingReviewV5,
            $functionCaller(...)
        );
        $vendorCanonicalNamingMigration = new VendorCanonicalNamingMigrationService(
            $vendorNamingAudit,
            $translatePressTitleInspector,
            $translationDictionary,
            $translationRegistrar,
            $functionCaller(...)
        );
        $vendorCanonicalNamingMigrationPage = new VendorCanonicalNamingMigrationPage(
            $vendorCanonicalNamingMigration,
            $functionCaller(...)
        );
        $vendorCanonicalNamingMigrationV2 = new VendorCanonicalNamingMigrationV2Service(
            $vendorNamingAudit,
            $translatePressTitleInspector,
            $translationDictionary,
            $translationRegistrar,
            $functionCaller(...)
        );
        $vendorCanonicalNamingMigrationV2Page = new VendorCanonicalNamingMigrationV2Page(
            $vendorCanonicalNamingMigrationV2,
            $functionCaller(...)
        );
        $elementorProTranslatePressPreflight = new ElementorProTranslatePressPreflightService(
            $translatePressTitleInspector,
            $translationDictionary
        );
        $elementorProTranslatePressPreflightPage = new ElementorProTranslatePressPreflightPage(
            $elementorProTranslatePressPreflight,
            $functionCaller(...)
        );
        $vendorCoverAudit = new VendorCoverAuditService(
            $functionCaller(...)
        );
        $vendorCoverAuditPage = new VendorCoverAuditPage(
            $vendorCoverAudit,
            $functionCaller(...)
        );
        $vendorCoverRenderer = new VendorCoverRenderer();
        $vendorCoverPreview = new VendorCoverPreviewService(
            $vendorCoverAudit,
            $vendorCoverRenderer,
            $functionCaller(...)
        );
        $vendorCoverGeneratorPage = new VendorCoverGeneratorPage(
            $vendorCoverPreview,
            $functionCaller(...)
        );
        $titleVersionAudit = new ProductTitleVersionAuditService(
            $functionCaller(...)
        );
        $titleVersionMigration = new ProductTitleVersionMigrationService(
            $functionCaller(...)
        );
        $titleVersionAuditPage = new ProductTitleVersionAuditPage(
            $titleVersionAudit,
            $titleVersionMigration,
            $functionCaller(...)
        );

        $registry->addSubmenu($page);
        $registry->addSubmenu($batchIntakePage);
        $registry->addSubmenu($editorialMigrationPage);
        $registry->addSubmenu($englishContentAuditPage);
        $registry->addSubmenu($updatePage);
        $registry->addSubmenu($updateScannerPage);
        $registry->addSubmenu($updateFullScannerPage);
        $registry->addSubmenu($updateQueuePage);
        $registry->addSubmenu($vendorNamingAuditPage);
        $registry->addSubmenu($titleVersionAuditPage);
        $registry->addSubmenu($vendorNamingReviewPage);
        $registry->addSubmenu($vendorNamingReviewV5Page);
        $registry->addSubmenu($vendorCanonicalNamingMigrationPage);
        $registry->addSubmenu($vendorCanonicalNamingMigrationV2Page);
        $registry->addSubmenu($elementorProTranslatePressPreflightPage);
        $registry->addSubmenu($vendorCoverAuditPage);
        $registry->addSubmenu($vendorCoverGeneratorPage);

        $this->container->set(EnvatoItemMapper::class, $mapper);
        $this->container->set(WordPressEnvatoTransport::class, $transport);
        $this->container->set(EnvatoClientInterface::class, $envatoClient);
        $this->container->set(EnvatoClient::class, $envatoClient);
        $this->container->set(
            EnvatoItemSearchResolver::class,
            $envatoSearchResolver
        );
        $this->container->set(
            CatalogTagRepositoryInterface::class,
            $tagRepository
        );
        $this->container->set(
            WordPressCatalogTagRepository::class,
            $tagRepository
        );
        $this->container->set(ExistingTagSelector::class, $tagSelector);
        $this->container->set(ExistingCatalogTagParser::class, $tagParser);
        $this->container->set(WordPressFunctionCaller::class, $functionCaller);
        $this->container->set(
            PreparedEnglishProductContent::class,
            $preparedEnglishContent
        );
        $this->container->set(
            ProductEditorialMigrationService::class,
            $editorialMigrationService
        );
        $this->container->set(
            ProductEditorialMigrationPage::class,
            $editorialMigrationPage
        );
        $this->container->set(ProductArchiveUploader::class, $archiveUploader);
        $this->container->set(
            ProductArchiveVersionInspector::class,
            $archiveVersionInspector
        );
        $this->container->set(
            ProductArchiveIdentityInspector::class,
            $archiveIdentityInspector
        );
        $this->container->set(ProductBatchIntakeScanner::class, $batchIntakeScanner);
        $this->container->set(ProductBatchIntakePage::class, $batchIntakePage);
        $this->container->set(ProductDraftGatewayInterface::class, $draftGateway);
        $this->container->set(WordPressWooCommerceDraftGateway::class, $draftGateway);
        $this->container->set(ProductDraftValidator::class, $draftValidator);
        $this->container->set(ProductTaxonomyWriter::class, $taxonomyWriter);
        $this->container->set(ProductMetadataWriter::class, $metadataWriter);
        $this->container->set(SureRankWriter::class, $sureRankWriter);
        $this->container->set(AdvancedLabelWriter::class, $labelWriter);
        $this->container->set(ProductDraftCreator::class, $draftCreator);
        $this->container->set(TranslationMapBuilder::class, $translationMapBuilder);
        $this->container->set(
            TranslationDictionaryInterface::class,
            $translationDictionary
        );
        $this->container->set(TranslatePressDictionary::class, $translationDictionary);
        $this->container->set(
            TranslationRegistrarInterface::class,
            $translationRegistrar
        );
        $this->container->set(TranslatePressRegistrar::class, $translationRegistrar);
        $this->container->set(
            TranslatePressProductTranslator::class,
            $translator
        );
        $this->container->set(
            EnglishContentAuditService::class,
            $englishContentAudit
        );
        $this->container->set(
            EnglishContentAuditPage::class,
            $englishContentAuditPage
        );
        $this->container->set(ProductManagerController::class, $controller);
        $this->container->set(ProductManagerPage::class, $page);
        $this->container->set(ProductVersionUpdater::class, $versionUpdater);
        $this->container->set(
            ProductArchiveUpdateCoordinator::class,
            $archiveUpdateCoordinator
        );
        $this->container->set(
            ProductBatchZipUpdateService::class,
            $batchZipUpdateService
        );
        $this->container->set(ProductUpdateEnvatoAdvisor::class, $updateAdvisor);
        $this->container->set(
            ProductUpdateManualCandidateBuilder::class,
            $manualCandidateBuilder
        );
        $this->container->set(ProductUpdatePage::class, $updatePage);
        $this->container->set(ProductUpdateScanner::class, $updateScanner);
        $this->container->set(ProductUpdateScannerPage::class, $updateScannerPage);
        $this->container->set(
            ProductUpdateFullScannerPage::class,
            $updateFullScannerPage
        );
        $this->container->set(ProductUpdateQueuePage::class, $updateQueuePage);
        $this->container->set(
            VendorProductNamingAuditService::class,
            $vendorNamingAudit
        );
        $this->container->set(
            VendorProductNamingMigrationService::class,
            $vendorNamingMigration
        );
        $this->container->set(
            VendorSalesPageNameInspector::class,
            $vendorSalesPageNameInspector
        );
        $this->container->set(
            TranslatePressTitleInspector::class,
            $translatePressTitleInspector
        );
        $this->container->set(
            VendorProductNamingReviewService::class,
            $vendorNamingReview
        );
        $this->container->set(
            VendorProductNamingReviewPage::class,
            $vendorNamingReviewPage
        );
        $this->container->set(
            VendorProductNamingReviewV5Service::class,
            $vendorNamingReviewV5
        );
        $this->container->set(
            VendorProductNamingReviewV5Page::class,
            $vendorNamingReviewV5Page
        );
        $this->container->set(
            VendorCanonicalNamingMigrationService::class,
            $vendorCanonicalNamingMigration
        );
        $this->container->set(
            VendorCanonicalNamingMigrationPage::class,
            $vendorCanonicalNamingMigrationPage
        );
        $this->container->set(
            VendorCanonicalNamingMigrationV2Service::class,
            $vendorCanonicalNamingMigrationV2
        );
        $this->container->set(
            VendorCanonicalNamingMigrationV2Page::class,
            $vendorCanonicalNamingMigrationV2Page
        );
        $this->container->set(
            ElementorProTranslatePressPreflightService::class,
            $elementorProTranslatePressPreflight
        );
        $this->container->set(
            ElementorProTranslatePressPreflightPage::class,
            $elementorProTranslatePressPreflightPage
        );
        $this->container->set(
            VendorCoverAuditService::class,
            $vendorCoverAudit
        );
        $this->container->set(
            VendorCoverAuditPage::class,
            $vendorCoverAuditPage
        );
        $this->container->set(
            VendorCoverRenderer::class,
            $vendorCoverRenderer
        );
        $this->container->set(
            VendorCoverPreviewService::class,
            $vendorCoverPreview
        );
        $this->container->set(
            VendorCoverGeneratorPage::class,
            $vendorCoverGeneratorPage
        );
        $this->container->set(
            VendorProductNamingAuditPage::class,
            $vendorNamingAuditPage
        );
        $this->container->set(
            ProductTitleVersionAuditService::class,
            $titleVersionAudit
        );
        $this->container->set(
            ProductTitleVersionMigrationService::class,
            $titleVersionMigration
        );
        $this->container->set(
            ProductTitleVersionAuditPage::class,
            $titleVersionAuditPage
        );
        $this->container->set(
            ProductUpdateQueueReturnNavigation::class,
            $updateQueueReturnNavigation
        );
    }

    public function boot(KernelInterface $kernel): void
    {
        $page = $this->container->get(ProductUpdateScannerPage::class);
        $englishContentAuditPage = $this->container->get(
            EnglishContentAuditPage::class
        );
        $vendorNamingAuditPage = $this->container->get(
            VendorProductNamingAuditPage::class
        );
        $vendorNamingReviewPage = $this->container->get(
            VendorProductNamingReviewPage::class
        );
        $vendorNamingReviewV5Page = $this->container->get(
            VendorProductNamingReviewV5Page::class
        );
        $vendorCoverAuditPage = $this->container->get(
            VendorCoverAuditPage::class
        );
        $titleVersionAuditPage = $this->container->get(
            ProductTitleVersionAuditPage::class
        );
        $returnNavigation = $this->container->get(
            ProductUpdateQueueReturnNavigation::class
        );
        $functionCaller = $this->container->get(WordPressFunctionCaller::class);
        $preparedEnglishContent = $this->container->get(
            PreparedEnglishProductContent::class
        );

        if (! $page instanceof ProductUpdateScannerPage) {
            throw new LogicException(
                'ProductUpdateScannerPage must be registered before boot.'
            );
        }

        if (! $englishContentAuditPage instanceof EnglishContentAuditPage) {
            throw new LogicException(
                'EnglishContentAuditPage must be registered before boot.'
            );
        }

        if (! $vendorNamingAuditPage instanceof VendorProductNamingAuditPage) {
            throw new LogicException(
                'VendorProductNamingAuditPage must be registered before boot.'
            );
        }

        if (! $vendorNamingReviewPage instanceof VendorProductNamingReviewPage) {
            throw new LogicException(
                'VendorProductNamingReviewPage must be registered before boot.'
            );
        }

        if (! $vendorNamingReviewV5Page instanceof VendorProductNamingReviewV5Page) {
            throw new LogicException(
                'VendorProductNamingReviewV5Page must be registered before boot.'
            );
        }

        if (! $vendorCoverAuditPage instanceof VendorCoverAuditPage) {
            throw new LogicException(
                'VendorCoverAuditPage must be registered before boot.'
            );
        }

        if (! $titleVersionAuditPage instanceof ProductTitleVersionAuditPage) {
            throw new LogicException(
                'ProductTitleVersionAuditPage must be registered before boot.'
            );
        }

        if (! $returnNavigation instanceof ProductUpdateQueueReturnNavigation) {
            throw new LogicException(
                'ProductUpdateQueueReturnNavigation must be registered before boot.'
            );
        }

        if (! $functionCaller instanceof WordPressFunctionCaller) {
            throw new LogicException(
                'WordPressFunctionCaller must be registered before boot.'
            );
        }

        if (! $preparedEnglishContent instanceof PreparedEnglishProductContent) {
            throw new LogicException(
                'PreparedEnglishProductContent must be registered before boot.'
            );
        }

        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_update_report',
            [$page, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_en_content_audit',
            [$englishContentAuditPage, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_vendor_naming_audit',
            [$vendorNamingAuditPage, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_vendor_naming_review_v4',
            [$vendorNamingReviewPage, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_vendor_naming_review_v5',
            [$vendorNamingReviewV5Page, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_vendor_cover_audit',
            [$vendorCoverAuditPage, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_post_wp_shop_pm_export_title_version_audit',
            [$titleVersionAuditPage, 'exportCsv']
        );
        $functionCaller(
            'add_action',
            'admin_notices',
            [$returnNavigation, 'renderReturnNotice']
        );
        $functionCaller(
            'add_action',
            'admin_footer',
            [$returnNavigation, 'injectQueueReturnState']
        );
        $functionCaller(
            'add_filter',
            'woocommerce_short_description',
            [$preparedEnglishContent, 'filterShortDescription'],
            999,
            1
        );
        $functionCaller(
            'add_filter',
            'woocommerce_product_get_short_description',
            [$preparedEnglishContent, 'filterShortDescription'],
            999,
            1
        );
        $functionCaller(
            'add_filter',
            'woocommerce_product_get_description',
            [$preparedEnglishContent, 'filterLongDescription'],
            999,
            1
        );
    }
}
