<?php

declare(strict_types=1);

namespace WPShop\App\Plugin;

use Closure;
use DateTimeImmutable;
use LogicException;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Database\WordPressTransactionManager;
use WPShop\App\Plugin\Manifest\ManifestServiceProvider;
use WPShop\App\Plugin\Release\ReleasePublicationService;
use WPShop\App\Plugin\Release\ReleasePublisherService;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Core\Container\ContainerInterface;
use WPShop\Core\Contracts\KernelInterface;
use WPShop\Core\Provider\AbstractServiceProvider;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Publisher\Assembly\PharZipPackageAssembler;
use WPShop\Publisher\Contracts\ArtifactManifestDecoratorInterface;
use WPShop\Publisher\Contracts\ArtifactStorageInterface;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Contracts\PackageEntryFilenameResolverInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\Contracts\PluginHeaderParserInterface;
use WPShop\Publisher\Contracts\PluginPackageValidatorInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Contracts\ThemePackageValidatorInterface;
use WPShop\Publisher\Manifest\JsonArtifactManifestDecorator;
use WPShop\Publisher\Parser\WordPressPluginHeaderParser;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;
use WPShop\Publisher\PublisherRegistry;
use WPShop\Publisher\Resolution\WordPressPackageEntryFilenameResolver;
use WPShop\Publisher\Source\LocalPackageSourceResolver;
use WPShop\Publisher\Storage\LocalArtifactStorage;
use WPShop\Publisher\Validation\WordPressPluginPackageValidator;
use WPShop\Publisher\Validation\WordPressThemePackageValidator;
use WPShop\Publisher\WordPressPluginPublisher;
use WPShop\Publisher\WordPressThemePublisher;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleasePublisherServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Policy\DefaultReleasePublicationPolicy;

final class PluginServiceProvider extends AbstractServiceProvider
{
    private ?BlueprintServiceProvider $blueprintProvider =
        null;

    private ?ReleaseServiceProvider $releaseProvider =
        null;

    private ?ManifestServiceProvider $manifestProvider =
        null;

    /**
     * @param Closure(): string $uuidGenerator
     * @param Closure(): DateTimeImmutable $clock
     * @param Closure(string): (int|bool) $query
     */
    public function __construct(
        ContainerInterface $container,
        private readonly DatabaseConnectionInterface $database,
        private readonly string $blueprintsTable,
        private readonly string $releasesTable,
        private readonly string $manifestsTable,
        private readonly string $sourceRoot,
        private readonly string $workspaceRoot,
        private readonly string $artifactRoot,
        private readonly Closure $uuidGenerator,
        private readonly Closure $clock,
        private readonly Closure $query
    ) {
        parent::__construct($container);
    }

    public function register(): void
    {
        $transactionManager =
            new WordPressTransactionManager(
                $this->query
            );

        $this->container->set(
            TransactionManagerInterface::class,
            $transactionManager
        );

        $this->container->set(
            WordPressTransactionManager::class,
            $transactionManager
        );

        $this->blueprintProvider =
            new BlueprintServiceProvider(
                $this->container,
                $this->database,
                $this->blueprintsTable,
                $this->uuidGenerator,
                $this->clock
            );

        $this->blueprintProvider->register();

        $this->container->set(
            BlueprintServiceProvider::class,
            $this->blueprintProvider
        );

        $this->releaseProvider =
            new ReleaseServiceProvider(
                $this->container,
                $this->database,
                $this->releasesTable,
                $this->clock
            );

        $this->releaseProvider->register();

        $this->container->set(
            ReleaseServiceProvider::class,
            $this->releaseProvider
        );

        $this->manifestProvider =
            new ManifestServiceProvider(
                $this->container,
                $this->database,
                $this->manifestsTable,
                $this->clock
            );

        $this->manifestProvider->register();

        $this->container->set(
            ManifestServiceProvider::class,
            $this->manifestProvider
        );

        $releaseRepository = $this->container->get(
            ReleaseRepositoryInterface::class
        );

        $blueprintRepository = $this->container->get(
            BlueprintRepositoryInterface::class
        );

        $manifestRepository = $this->container->get(
            ManifestRepositoryInterface::class
        );

        if (
            ! $releaseRepository instanceof
                ReleaseRepositoryInterface
            || ! $blueprintRepository instanceof
                BlueprintRepositoryInterface
            || ! $manifestRepository instanceof
                ManifestRepositoryInterface
        ) {
            throw new LogicException(
                'Plugin domain repositories must be registered '
                . 'before publication services.'
            );
        }

        $publicationService =
            new ReleasePublicationService(
                $releaseRepository,
                $blueprintRepository,
                $manifestRepository,
                $transactionManager
            );

        $this->container->set(
            ReleasePublicationServiceInterface::class,
            $publicationService
        );

        $this->container->set(
            ReleasePublicationService::class,
            $publicationService
        );

        $entryFilenameResolver =
            new WordPressPackageEntryFilenameResolver();

        $this->container->set(
            PackageEntryFilenameResolverInterface::class,
            $entryFilenameResolver
        );

        $this->container->set(
            WordPressPackageEntryFilenameResolver::class,
            $entryFilenameResolver
        );

        $sourceResolver = new LocalPackageSourceResolver(
            $this->sourceRoot,
            $entryFilenameResolver
        );

        $this->container->set(
            PackageSourceResolverInterface::class,
            $sourceResolver
        );

        $this->container->set(
            LocalPackageSourceResolver::class,
            $sourceResolver
        );

        $packageAssembler = new PharZipPackageAssembler(
            $this->workspaceRoot
        );

        $this->container->set(
            PackageAssemblerInterface::class,
            $packageAssembler
        );

        $this->container->set(
            PharZipPackageAssembler::class,
            $packageAssembler
        );

        $pluginHeaderParser =
            new WordPressPluginHeaderParser();

        $this->container->set(
            PluginHeaderParserInterface::class,
            $pluginHeaderParser
        );

        $this->container->set(
            WordPressPluginHeaderParser::class,
            $pluginHeaderParser
        );

        $pluginPackageValidator =
            new WordPressPluginPackageValidator(
                $pluginHeaderParser
            );

        $this->container->set(
            PluginPackageValidatorInterface::class,
            $pluginPackageValidator
        );

        $this->container->set(
            WordPressPluginPackageValidator::class,
            $pluginPackageValidator
        );

        $themeHeaderParser =
            new WordPressThemeHeaderParser();

        $this->container->set(
            ThemeHeaderParserInterface::class,
            $themeHeaderParser
        );

        $this->container->set(
            WordPressThemeHeaderParser::class,
            $themeHeaderParser
        );

        $themePackageValidator =
            new WordPressThemePackageValidator(
                $themeHeaderParser
            );

        $this->container->set(
            ThemePackageValidatorInterface::class,
            $themePackageValidator
        );

        $this->container->set(
            WordPressThemePackageValidator::class,
            $themePackageValidator
        );

        $pluginPublisher = new WordPressPluginPublisher(
            $sourceResolver,
            $pluginPackageValidator,
            $packageAssembler
        );

        $this->container->set(
            WordPressPluginPublisher::class,
            $pluginPublisher
        );

        $themePublisher = new WordPressThemePublisher(
            $sourceResolver,
            $themePackageValidator,
            $packageAssembler
        );

        $this->container->set(
            WordPressThemePublisher::class,
            $themePublisher
        );

        $publisherRegistry = new PublisherRegistry();

        $publisherRegistry->register(
            WordPressPluginPublisher::BLUEPRINT_TYPE,
            $pluginPublisher
        );

        $publisherRegistry->register(
            WordPressThemePublisher::BLUEPRINT_TYPE,
            $themePublisher
        );

        $this->container->set(
            PublisherRegistryInterface::class,
            $publisherRegistry
        );

        $this->container->set(
            PublisherRegistry::class,
            $publisherRegistry
        );

        $artifactStorage = new LocalArtifactStorage(
            $this->artifactRoot
        );

        $this->container->set(
            ArtifactStorageInterface::class,
            $artifactStorage
        );

        $this->container->set(
            LocalArtifactStorage::class,
            $artifactStorage
        );

        $artifactManifestDecorator =
            new JsonArtifactManifestDecorator();

        $this->container->set(
            ArtifactManifestDecoratorInterface::class,
            $artifactManifestDecorator
        );

        $this->container->set(
            JsonArtifactManifestDecorator::class,
            $artifactManifestDecorator
        );

        $publicationPolicy =
            new DefaultReleasePublicationPolicy();

        $this->container->set(
            ReleasePublicationPolicyInterface::class,
            $publicationPolicy
        );

        $this->container->set(
            DefaultReleasePublicationPolicy::class,
            $publicationPolicy
        );

        $publisherService =
            new ReleasePublisherService(
                $releaseRepository,
                $blueprintRepository,
                $publicationPolicy,
                $publisherRegistry,
                $artifactStorage,
                $artifactManifestDecorator,
                $publicationService
            );

        $this->container->set(
            ReleasePublisherServiceInterface::class,
            $publisherService
        );

        $this->container->set(
            ReleasePublisherService::class,
            $publisherService
        );
    }

    public function boot(KernelInterface $kernel): void
    {
        if (
            !$this->blueprintProvider instanceof \WPShop\App\Plugin\Blueprint\BlueprintServiceProvider
            || !$this->releaseProvider instanceof \WPShop\App\Plugin\Release\ReleaseServiceProvider
            || !$this->manifestProvider instanceof \WPShop\App\Plugin\Manifest\ManifestServiceProvider
        ) {
            throw new LogicException(
                'PluginServiceProvider must be registered before it is booted.'
            );
        }

        $this->blueprintProvider->boot($kernel);
        $this->releaseProvider->boot($kernel);
        $this->manifestProvider->boot($kernel);
    }
}
