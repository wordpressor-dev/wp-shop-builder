<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin;

use Closure;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WPShop\App\Plugin\Blueprint\BlueprintServiceProvider;
use WPShop\App\Plugin\Blueprint\WordPressBlueprintRepository;
use WPShop\App\Plugin\Database\Contracts\DatabaseConnectionInterface;
use WPShop\App\Plugin\Database\Contracts\TransactionManagerInterface;
use WPShop\App\Plugin\Database\WordPressTransactionManager;
use WPShop\App\Plugin\Manifest\ManifestServiceProvider;
use WPShop\App\Plugin\Manifest\WordPressManifestRepository;
use WPShop\App\Plugin\PluginServiceProvider;
use WPShop\App\Plugin\Release\ReleasePublicationService;
use WPShop\App\Plugin\Release\ReleasePublisherService;
use WPShop\App\Plugin\Release\ReleaseServiceProvider;
use WPShop\App\Plugin\Release\WordPressReleaseRepository;
use WPShop\Blueprint\Contracts\BlueprintRepositoryInterface;
use WPShop\Blueprint\Contracts\BlueprintServiceInterface;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\Manifest\Contracts\ManifestRepositoryInterface;
use WPShop\Manifest\Contracts\ManifestServiceInterface;
use WPShop\Publisher\Assembly\PharZipPackageAssembler;
use WPShop\Publisher\Contracts\ArtifactManifestDecoratorInterface;
use WPShop\Publisher\Contracts\ArtifactStorageInterface;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Contracts\PackageEntryFilenameResolverInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\Contracts\PluginHeaderParserInterface;
use WPShop\Publisher\Contracts\PluginPackageValidatorInterface;
use WPShop\Publisher\Contracts\PublisherRegistryInterface;
use WPShop\Publisher\Contracts\ThemeCompatibilityValidatorInterface;
use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Contracts\ThemePackageValidatorInterface;
use WPShop\Publisher\Contracts\ThemeStructureValidatorInterface;
use WPShop\Publisher\Manifest\JsonArtifactManifestDecorator;
use WPShop\Publisher\Parser\WordPressPluginHeaderParser;
use WPShop\Publisher\Parser\WordPressThemeHeaderParser;
use WPShop\Publisher\PublisherRegistry;
use WPShop\Publisher\Resolution\WordPressPackageEntryFilenameResolver;
use WPShop\Publisher\Source\LocalPackageSourceResolver;
use WPShop\Publisher\Storage\LocalArtifactStorage;
use WPShop\Publisher\Validation\WordPressPluginPackageValidator;
use WPShop\Publisher\Validation\WordPressThemeCompatibilityValidator;
use WPShop\Publisher\Validation\WordPressThemePackageValidator;
use WPShop\Publisher\Validation\WordPressThemeStructureValidator;
use WPShop\Publisher\WordPressPluginPublisher;
use WPShop\Publisher\WordPressThemePublisher;
use WPShop\Release\Contracts\ReleasePublicationPolicyInterface;
use WPShop\Release\Contracts\ReleasePublicationServiceInterface;
use WPShop\Release\Contracts\ReleasePublisherServiceInterface;
use WPShop\Release\Contracts\ReleaseRepositoryInterface;
use WPShop\Release\Contracts\ReleaseServiceInterface;
use WPShop\Release\Policy\DefaultReleasePublicationPolicy;

final class PluginServiceProviderTest extends TestCase
{
    public function testRegistersPluginDomainServices(): void
    {
        $container = new Container();

        $database =
            new PluginProviderDatabaseConnection();

        $provider = $this->provider(
            $container,
            $database
        );

        $provider->register();

        self::assertSame(
            $database,
            $container->get(
                DatabaseConnectionInterface::class
            )
        );

        self::assertInstanceOf(
            BlueprintServiceProvider::class,
            $container->get(
                BlueprintServiceProvider::class
            )
        );

        self::assertInstanceOf(
            ReleaseServiceProvider::class,
            $container->get(
                ReleaseServiceProvider::class
            )
        );

        self::assertInstanceOf(
            ManifestServiceProvider::class,
            $container->get(
                ManifestServiceProvider::class
            )
        );

        self::assertInstanceOf(
            BlueprintServiceInterface::class,
            $container->get(
                BlueprintServiceInterface::class
            )
        );

        self::assertInstanceOf(
            ReleaseServiceInterface::class,
            $container->get(
                ReleaseServiceInterface::class
            )
        );

        self::assertInstanceOf(
            ManifestServiceInterface::class,
            $container->get(
                ManifestServiceInterface::class
            )
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressBlueprintRepository::class
            ),
            $database,
            'wp_wps_blueprints'
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressReleaseRepository::class
            ),
            $database,
            'wp_wps_releases'
        );

        $this->assertRepositoryConfiguration(
            $container->get(
                WordPressManifestRepository::class
            ),
            $database,
            'wp_wps_manifests'
        );

        $provider->boot(new Kernel());
    }

    public function testRegistersPublicationWorkflowServices(): void
    {
        $container = new Container();

        $database =
            new PluginProviderDatabaseConnection();

        /** @var list<string> $queries */
        $queries = [];

        $query = static function (
            string $sql
        ) use (&$queries): int {
            $queries[] = $sql;

            return 0;
        };

        $provider = $this->provider(
            $container,
            $database,
            $query
        );

        $provider->register();

        $transactionManager = $container->get(
            TransactionManagerInterface::class
        );

        if (
            ! $transactionManager instanceof
                WordPressTransactionManager
        ) {
            self::fail(
                'WordPress transaction manager was not registered.'
            );
        }

        self::assertSame(
            $transactionManager,
            $container->get(
                WordPressTransactionManager::class
            )
        );

        self::assertSame(
            'publication-result',
            $transactionManager->transactional(
                static fn(): string =>
                    'publication-result'
            )
        );

        self::assertSame(
            [
                'START TRANSACTION',
                'COMMIT',
            ],
            $queries
        );

        $publicationService = $container->get(
            ReleasePublicationServiceInterface::class
        );

        if (
            ! $publicationService instanceof
                ReleasePublicationService
        ) {
            self::fail(
                'Release publication service was not registered.'
            );
        }

        self::assertSame(
            $publicationService,
            $container->get(
                ReleasePublicationService::class
            )
        );

        self::assertSame(
            $container->get(
                ReleaseRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'releaseRepository'
            )
        );

        self::assertSame(
            $container->get(
                BlueprintRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'blueprintRepository'
            )
        );

        self::assertSame(
            $container->get(
                ManifestRepositoryInterface::class
            ),
            $this->property(
                $publicationService,
                'manifestRepository'
            )
        );

        self::assertSame(
            $transactionManager,
            $this->property(
                $publicationService,
                'transactionManager'
            )
        );

        $publisherRegistry = $container->get(
            PublisherRegistryInterface::class
        );

        if (
            ! $publisherRegistry instanceof
                PublisherRegistry
        ) {
            self::fail(
                'Publisher registry was not registered.'
            );
        }

        self::assertSame(
            $publisherRegistry,
            $container->get(
                PublisherRegistry::class
            )
        );

        $entryFilenameResolver = $container->get(
            PackageEntryFilenameResolverInterface::class
        );

        if (
            ! $entryFilenameResolver instanceof
                WordPressPackageEntryFilenameResolver
        ) {
            self::fail(
                'WordPress package entry filename resolver '
                . 'was not registered.'
            );
        }

        self::assertSame(
            $entryFilenameResolver,
            $container->get(
                WordPressPackageEntryFilenameResolver::class
            )
        );

        $sourceResolver = $container->get(
            PackageSourceResolverInterface::class
        );

        if (
            ! $sourceResolver instanceof
                LocalPackageSourceResolver
        ) {
            self::fail(
                'Local package source resolver was not registered.'
            );
        }

        self::assertSame(
            $sourceResolver,
            $container->get(
                LocalPackageSourceResolver::class
            )
        );

        self::assertSame(
            sys_get_temp_dir()
                . '/wp-shop-builder-sources',
            $this->property(
                $sourceResolver,
                'root'
            )
        );

        self::assertSame(
            $entryFilenameResolver,
            $this->property(
                $sourceResolver,
                'entryFilenameResolver'
            )
        );

        $packageAssembler = $container->get(
            PackageAssemblerInterface::class
        );

        if (
            ! $packageAssembler instanceof
                PharZipPackageAssembler
        ) {
            self::fail(
                'Phar ZIP package assembler was not registered.'
            );
        }

        self::assertSame(
            $packageAssembler,
            $container->get(
                PharZipPackageAssembler::class
            )
        );

        self::assertSame(
            sys_get_temp_dir()
                . '/wp-shop-builder-work',
            $this->property(
                $packageAssembler,
                'workspaceRoot'
            )
        );

        $pluginHeaderParser = $container->get(
            PluginHeaderParserInterface::class
        );

        if (
            ! $pluginHeaderParser instanceof
                WordPressPluginHeaderParser
        ) {
            self::fail(
                'WordPress plugin header parser '
                . 'was not registered.'
            );
        }

        self::assertSame(
            $pluginHeaderParser,
            $container->get(
                WordPressPluginHeaderParser::class
            )
        );

        $pluginPackageValidator = $container->get(
            PluginPackageValidatorInterface::class
        );

        if (
            ! $pluginPackageValidator instanceof
                WordPressPluginPackageValidator
        ) {
            self::fail(
                'WordPress plugin package validator '
                . 'was not registered.'
            );
        }

        self::assertSame(
            $pluginPackageValidator,
            $container->get(
                WordPressPluginPackageValidator::class
            )
        );

        self::assertSame(
            $pluginHeaderParser,
            $this->property(
                $pluginPackageValidator,
                'headerParser'
            )
        );

        $themeHeaderParser = $container->get(
            ThemeHeaderParserInterface::class
        );

        if (
            ! $themeHeaderParser instanceof
                WordPressThemeHeaderParser
        ) {
            self::fail(
                'WordPress theme header parser '
                . 'was not registered.'
            );
        }

        self::assertSame(
            $themeHeaderParser,
            $container->get(
                WordPressThemeHeaderParser::class
            )
        );

        $themeCompatibilityValidator = $container->get(
            ThemeCompatibilityValidatorInterface::class
        );

        if (
            ! $themeCompatibilityValidator instanceof
                WordPressThemeCompatibilityValidator
        ) {
            self::fail(
                'WordPress theme compatibility validator '
                    . 'was not registered.'
            );
        }

        self::assertSame(
            $themeCompatibilityValidator,
            $container->get(
                WordPressThemeCompatibilityValidator::class
            )
        );

        $themeStructureValidator = $container->get(
            ThemeStructureValidatorInterface::class
        );

        if (
            ! $themeStructureValidator instanceof
                WordPressThemeStructureValidator
        ) {
            self::fail(
                'WordPress theme structure validator '
                    . 'was not registered.'
            );
        }

        self::assertSame(
            $themeStructureValidator,
            $container->get(
                WordPressThemeStructureValidator::class
            )
        );

        $themePackageValidator = $container->get(
            ThemePackageValidatorInterface::class
        );

        if (
            ! $themePackageValidator instanceof
                WordPressThemePackageValidator
        ) {
            self::fail(
                'WordPress theme package validator '
                . 'was not registered.'
            );
        }

        self::assertSame(
            $themePackageValidator,
            $container->get(
                WordPressThemePackageValidator::class
            )
        );

        self::assertSame(
            $themeHeaderParser,
            $this->property(
                $themePackageValidator,
                'headerParser'
            )
        );

        self::assertSame(
            $themeCompatibilityValidator,
            $this->property(
                $themePackageValidator,
                'compatibilityValidator'
            )
        );

        self::assertSame(
            $themeStructureValidator,
            $this->property(
                $themePackageValidator,
                'structureValidator'
            )
        );

        $pluginPublisher = $container->get(
            WordPressPluginPublisher::class
        );

        self::assertSame(
            $sourceResolver,
            $this->property(
                $pluginPublisher,
                'sourceResolver'
            )
        );

        self::assertSame(
            $pluginPackageValidator,
            $this->property(
                $pluginPublisher,
                'packageValidator'
            )
        );

        self::assertSame(
            $packageAssembler,
            $this->property(
                $pluginPublisher,
                'packageAssembler'
            )
        );

        self::assertSame(
            $pluginPublisher,
            $publisherRegistry->publisherFor('plugin')
        );

        $themePublisher = $container->get(
            WordPressThemePublisher::class
        );

        self::assertSame(
            $sourceResolver,
            $this->property(
                $themePublisher,
                'sourceResolver'
            )
        );

        self::assertSame(
            $themePackageValidator,
            $this->property(
                $themePublisher,
                'packageValidator'
            )
        );

        self::assertSame(
            $packageAssembler,
            $this->property(
                $themePublisher,
                'packageAssembler'
            )
        );

        self::assertSame(
            $themePublisher,
            $publisherRegistry->publisherFor('theme')
        );

        $artifactStorage = $container->get(
            ArtifactStorageInterface::class
        );

        if (
            ! $artifactStorage instanceof
                LocalArtifactStorage
        ) {
            self::fail(
                'Local artifact storage was not registered.'
            );
        }

        self::assertSame(
            $artifactStorage,
            $container->get(
                LocalArtifactStorage::class
            )
        );

        self::assertSame(
            sys_get_temp_dir()
                . '/wp-shop-builder-artifacts',
            $this->property(
                $artifactStorage,
                'root'
            )
        );

        $artifactManifestDecorator = $container->get(
            ArtifactManifestDecoratorInterface::class
        );

        if (
            ! $artifactManifestDecorator instanceof
                JsonArtifactManifestDecorator
        ) {
            self::fail(
                'Artifact manifest decorator was not registered.'
            );
        }

        self::assertSame(
            $artifactManifestDecorator,
            $container->get(
                JsonArtifactManifestDecorator::class
            )
        );

        $publicationPolicy = $container->get(
            ReleasePublicationPolicyInterface::class
        );

        if (
            ! $publicationPolicy instanceof
                DefaultReleasePublicationPolicy
        ) {
            self::fail(
                'Release publication policy was not registered.'
            );
        }

        self::assertSame(
            $publicationPolicy,
            $container->get(
                DefaultReleasePublicationPolicy::class
            )
        );

        $publisherService = $container->get(
            ReleasePublisherServiceInterface::class
        );

        if (
            ! $publisherService instanceof
                ReleasePublisherService
        ) {
            self::fail(
                'Release publisher service was not registered.'
            );
        }

        self::assertSame(
            $publisherService,
            $container->get(
                ReleasePublisherService::class
            )
        );

        self::assertSame(
            $container->get(
                ReleaseRepositoryInterface::class
            ),
            $this->property(
                $publisherService,
                'releaseRepository'
            )
        );

        self::assertSame(
            $container->get(
                BlueprintRepositoryInterface::class
            ),
            $this->property(
                $publisherService,
                'blueprintRepository'
            )
        );

        self::assertSame(
            $publicationPolicy,
            $this->property(
                $publisherService,
                'publicationPolicy'
            )
        );

        self::assertSame(
            $publisherRegistry,
            $this->property(
                $publisherService,
                'publisherRegistry'
            )
        );

        self::assertSame(
            $artifactStorage,
            $this->property(
                $publisherService,
                'artifactStorage'
            )
        );

        self::assertSame(
            $artifactManifestDecorator,
            $this->property(
                $publisherService,
                'artifactManifestDecorator'
            )
        );

        self::assertSame(
            $publicationService,
            $this->property(
                $publisherService,
                'publicationService'
            )
        );
    }

    public function testBootRequiresRegistration(): void
    {
        $container = new Container();

        $provider = $this->provider(
            $container,
            new PluginProviderDatabaseConnection()
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'PluginServiceProvider must be registered before it is booted.'
        );

        $provider->boot(new Kernel());
    }

    private function provider(
        Container $container,
        PluginProviderDatabaseConnection $database,
        ?Closure $query = null
    ): PluginServiceProvider {
        return new PluginServiceProvider(
            $container,
            $database,
            'wp_wps_blueprints',
            'wp_wps_releases',
            'wp_wps_manifests',
            sys_get_temp_dir()
                . '/wp-shop-builder-sources',
            sys_get_temp_dir()
                . '/wp-shop-builder-work',
            sys_get_temp_dir()
                . '/wp-shop-builder-artifacts',
            static fn(): string =>
                '123e4567-e89b-12d3-a456-426614174000',
            static fn(): DateTimeImmutable =>
                new DateTimeImmutable(
                    '2026-08-01 12:00:00'
                ),
            $query ?? static fn(string $sql): int => 0
        );
    }

    private function assertRepositoryConfiguration(
        object $repository,
        PluginProviderDatabaseConnection $database,
        string $table
    ): void {
        self::assertSame(
            $database,
            $this->property(
                $repository,
                'database'
            )
        );

        self::assertSame(
            $table,
            $this->property(
                $repository,
                'table'
            )
        );
    }

    private function property(
        object $object,
        string $name
    ): mixed {
        $property = new ReflectionProperty(
            $object,
            $name
        );

        return $property->getValue($object);
    }
}

final class PluginProviderDatabaseConnection implements
    DatabaseConnectionInterface
{
    public function insert(
        string $table,
        array $data,
        array $formats
    ): int {
        return 1;
    }

    public function update(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        return 0;
    }

    public function fetchOne(
        string $sql,
        array $parameters = []
    ): ?array {
        return null;
    }

    public function fetchAll(
        string $sql,
        array $parameters = []
    ): array {
        return [];
    }

    public function fetchInteger(
        string $sql,
        array $parameters = []
    ): int {
        return 0;
    }
}
