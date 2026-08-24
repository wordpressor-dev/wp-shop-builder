<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

use LogicException;
use WPShop\App\Plugin\Admin\ProductManagerPage;
use WPShop\App\Plugin\Admin\ProductUpdatePage;
use WPShop\App\Plugin\Admin\ProductUpdateScannerPage;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\Draft\Contracts\ProductDraftGatewayInterface;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\WordPressEnvatoTransport;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationDictionaryInterface;
use WPShop\App\Plugin\ProductManager\Translation\Contracts\TranslationRegistrarInterface;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressDictionary;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressRegistrar;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateManualCandidateBuilder;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateScanner;
use WPShop\App\Plugin\ProductManager\Update\ProductVersionUpdater;
use WPShop\App\Plugin\ProductManager\WordPress\WordPressFunctionCaller;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\WordPress\Admin\AdminPageRegistry;

final class ProductManagerServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $registry = $this->container->get(
            AdminPageRegistry::class
        );
        $database = $this->container->get(
            DatabaseConnectionInterface::class
        );

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
        $envatoClient = new EnvatoClient(
            $transport(...),
            $mapper
        );
        $tagRepository = new WordPressCatalogTagRepository();
        $tagSelector = new ExistingTagSelector($tagRepository);
        $tagParser = new ExistingCatalogTagParser($tagRepository);
        $functionCaller = new WordPressFunctionCaller();
        $draftGateway = new WordPressWooCommerceDraftGateway(
            $functionCaller(...)
        );
        $draftValidator = new ProductDraftValidator();
        $taxonomyWriter = new ProductTaxonomyWriter(
            $functionCaller(...)
        );
        $metadataWriter = new ProductMetadataWriter(
            $functionCaller(...)
        );
        $sureRankWriter = new SureRankWriter(
            $functionCaller(...)
        );
        $labelWriter = new AdvancedLabelWriter(
            $functionCaller(...)
        );
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
        $controller = new ProductManagerController(
            $envatoClient,
            $tagSelector,
            $draftCreator,
            $translator,
            $tagParser
        );
        $page = new ProductManagerPage(
            $controller,
            $functionCaller(...)
        );
        $versionUpdater = new ProductVersionUpdater(
            $functionCaller(...)
        );
        $updateAdvisor = new ProductUpdateEnvatoAdvisor(
            $envatoClient
        );
        $manualCandidateBuilder = new ProductUpdateManualCandidateBuilder();
        $updatePage = new ProductUpdatePage(
            $versionUpdater,
            $updateAdvisor,
            $manualCandidateBuilder,
            $functionCaller(...)
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

        $registry->addSubmenu($page);
        $registry->addSubmenu($updatePage);
        $registry->addSubmenu($updateScannerPage);

        $this->container->set(
            EnvatoItemMapper::class,
            $mapper
        );
        $this->container->set(
            WordPressEnvatoTransport::class,
            $transport
        );
        $this->container->set(
            EnvatoClientInterface::class,
            $envatoClient
        );
        $this->container->set(
            EnvatoClient::class,
            $envatoClient
        );
        $this->container->set(
            CatalogTagRepositoryInterface::class,
            $tagRepository
        );
        $this->container->set(
            WordPressCatalogTagRepository::class,
            $tagRepository
        );
        $this->container->set(
            ExistingTagSelector::class,
            $tagSelector
        );
        $this->container->set(
            ExistingCatalogTagParser::class,
            $tagParser
        );
        $this->container->set(
            WordPressFunctionCaller::class,
            $functionCaller
        );
        $this->container->set(
            ProductDraftGatewayInterface::class,
            $draftGateway
        );
        $this->container->set(
            WordPressWooCommerceDraftGateway::class,
            $draftGateway
        );
        $this->container->set(
            ProductDraftValidator::class,
            $draftValidator
        );
        $this->container->set(
            ProductTaxonomyWriter::class,
            $taxonomyWriter
        );
        $this->container->set(
            ProductMetadataWriter::class,
            $metadataWriter
        );
        $this->container->set(
            SureRankWriter::class,
            $sureRankWriter
        );
        $this->container->set(
            AdvancedLabelWriter::class,
            $labelWriter
        );
        $this->container->set(
            ProductDraftCreator::class,
            $draftCreator
        );
        $this->container->set(
            TranslationMapBuilder::class,
            $translationMapBuilder
        );
        $this->container->set(
            TranslationDictionaryInterface::class,
            $translationDictionary
        );
        $this->container->set(
            TranslatePressDictionary::class,
            $translationDictionary
        );
        $this->container->set(
            TranslationRegistrarInterface::class,
            $translationRegistrar
        );
        $this->container->set(
            TranslatePressRegistrar::class,
            $translationRegistrar
        );
        $this->container->set(
            TranslatePressProductTranslator::class,
            $translator
        );
        $this->container->set(
            ProductManagerController::class,
            $controller
        );
        $this->container->set(
            ProductManagerPage::class,
            $page
        );
        $this->container->set(
            ProductVersionUpdater::class,
            $versionUpdater
        );
        $this->container->set(
            ProductUpdateEnvatoAdvisor::class,
            $updateAdvisor
        );
        $this->container->set(
            ProductUpdateManualCandidateBuilder::class,
            $manualCandidateBuilder
        );
        $this->container->set(
            ProductUpdatePage::class,
            $updatePage
        );
        $this->container->set(
            ProductUpdateScanner::class,
            $updateScanner
        );
        $this->container->set(
            ProductUpdateScannerPage::class,
            $updateScannerPage
        );
    }

    public function boot(KernelInterface $kernel): void
    {
    }
}
