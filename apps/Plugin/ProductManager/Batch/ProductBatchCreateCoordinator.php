<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Closure;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Admin\ProductManagerController;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductDownloadUrl;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftValidator;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;
use WPShop\App\Plugin\ProductManager\Draft\WordPressWooCommerceDraftGateway;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemMapper;
use WPShop\App\Plugin\ProductManager\Envato\WordPressEnvatoTransport;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Tags\WordPressCatalogTagRepository;
use WPShop\App\Plugin\ProductManager\Write\AdvancedLabelWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductMetadataWriter;
use WPShop\App\Plugin\ProductManager\Write\ProductTaxonomyWriter;
use WPShop\App\Plugin\ProductManager\Write\SureRankWriter;

final class ProductBatchCreateCoordinator
{
    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call,
        private readonly ProductBatchIntakeScanner $scanner,
        private readonly ?ProductArchiveIdentityInspector $identityInspector = null,
        private readonly ?ProductManagerController $controller = null
    ) {
    }

    public function createDraft(
        string $uploadsBaseDir,
        string $folder,
        string $filename,
        string $itemReference
    ): ProductDraftResult {
        try {
            $folder = $this->normalizeFolder($folder);
            $filename = $this->normalizeFilename($filename);
            $sourcePath = $this->sourcePath(
                $uploadsBaseDir,
                $folder,
                $filename
            );

            if (! is_file($sourcePath)) {
                return $this->failure('BATCH CREATE = SOURCE ZIP NOT FOUND');
            }

            $row = $this->rowByFilename(
                $uploadsBaseDir,
                $folder,
                $filename
            );

            if ($row === null) {
                return $this->failure('BATCH CREATE = ZIP NOT FOUND IN SCAN');
            }

            if ($row['productId'] > 0) {
                return $this->failure(
                    'BATCH CREATE = EXISTING PRODUCT MATCHED; USE UPDATE'
                );
            }

            if ($row['productType'] === '') {
                return $this->failure(
                    'BATCH CREATE = PRODUCT TYPE NOT DETECTED'
                );
            }

            if (! $this->validZipSignature($sourcePath)) {
                return $this->failure('BATCH CREATE = INVALID ZIP SIGNATURE');
            }

            $reference = $this->resolveItemReference(
                $itemReference,
                $row['itemId'],
                $row['productType']
            );

            if ($reference === '') {
                return $this->failure(
                    'BATCH CREATE = ENVATO URL OR ITEM ID REQUIRED'
                );
            }

            $token = $this->token();

            if ($token === '') {
                return $this->failure(
                    'BATCH CREATE = ENVATO TOKEN REQUIRED'
                );
            }

            $controller = $this->controller ?? $this->buildController();
            $autofill = $controller->autofill($reference, $token);

            if (! $autofill->success) {
                return new ProductDraftResult(
                    false,
                    null,
                    array_merge(
                        [
                            'BATCH CREATE = RECEIVED',
                            'BATCH ZIP = ' . $filename,
                        ],
                        $autofill->logs,
                        ['NO PRODUCT WRITTEN = YES']
                    )
                );
            }

            $fields = $autofill->fields;
            $itemId = (int) ($fields['item_id'] ?? 0);

            if ($itemId <= 0) {
                return $this->failure('BATCH CREATE = ENVATO ITEM ID MISSING');
            }

            if ($row['itemId'] > 0 && $itemId !== $row['itemId']) {
                return $this->failure(
                    'BATCH CREATE = ENVATO ITEM ID DOES NOT MATCH ZIP FILENAME'
                );
            }

            if ($this->productIdByItemId($itemId) > 0) {
                return $this->failure(
                    'BATCH CREATE = ITEM ID ALREADY EXISTS IN CATALOG'
                );
            }

            $baseTitle = trim((string) ($fields['base_title'] ?? ''));
            $salesPage = trim((string) ($fields['sales_page'] ?? ''));
            $envatoType = CatalogProductType::infer($baseTitle, $salesPage);

            if ($envatoType !== $row['productType']) {
                return $this->failure(
                    'BATCH CREATE = ZIP TYPE DOES NOT MATCH ENVATO ITEM TYPE'
                );
            }

            $identityInspector = $this->identityInspector
                ?? new ProductArchiveIdentityInspector();
            $identity = $identityInspector->inspect(
                $sourcePath,
                $filename
            );

            if (
                $identity->success
                && $identity->name !== ''
                && ! $this->identityMatches($identity->name, $baseTitle)
            ) {
                return $this->failure(
                    'BATCH CREATE = ZIP IDENTITY DOES NOT MATCH ENVATO TITLE'
                );
            }

            $version = $envatoType === CatalogProductType::TEMPLATE_KIT
                ? ''
                : trim($row['detectedVersion']);

            if (
                $envatoType !== CatalogProductType::TEMPLATE_KIT
                && $version === ''
            ) {
                return $this->failure(
                    'BATCH CREATE = ZIP VERSION NOT DETECTED'
                );
            }

            $sourceUpdateDate = trim((string) (
                $fields['source_update_date'] ?? ''
            ));

            if ($sourceUpdateDate === '') {
                return $this->failure(
                    'BATCH CREATE = ENVATO UPDATE DATE NOT PROVIDED'
                );
            }

            $skuFilename = ProductSkuFilename::build(
                $itemId,
                $salesPage,
                $version
            );
            $uploads = ($this->call)('wp_upload_dir');

            if (! is_array($uploads)) {
                return $this->failure(
                    'BATCH CREATE = WORDPRESS UPLOADS UNAVAILABLE'
                );
            }

            $baseDir = trim((string) ($uploads['basedir'] ?? ''));
            $baseUrl = trim((string) ($uploads['baseurl'] ?? ''));

            if ($baseDir === '' || $baseUrl === '') {
                return $this->failure(
                    'BATCH CREATE = UPLOAD BASE PATH OR URL MISSING'
                );
            }

            $storage = CatalogProductType::storageFolder($envatoType);
            $vendor = ProductDownloadUrl::vendorFolder($skuFilename);
            $storagePath = $storage
                . ($vendor !== '' ? '/' . $vendor : '');
            $targetDir = rtrim($baseDir, '/\\')
                . '/woocommerce_uploads/'
                . $storagePath
                . '/'
                . $itemId;
            $targetPath = $targetDir . '/' . $skuFilename;

            if ((bool) ($this->call)('file_exists', $targetPath)) {
                return $this->failure(
                    'BATCH CREATE = TARGET ARCHIVE ALREADY EXISTS; REVIEW REQUIRED'
                );
            }

            $downloadUrl = ProductDownloadUrl::build(
                $baseUrl,
                $envatoType,
                $itemId,
                $skuFilename
            );

            if ($downloadUrl === '') {
                return $this->failure(
                    'BATCH CREATE = DOWNLOAD URL BUILD FAILED'
                );
            }

            $developer = trim((string) ($fields['developer'] ?? ''));
            $content = $this->editorialContent(
                $baseTitle,
                $developer,
                $envatoType
            );

            try {
                $tags = $controller->parseExistingTags(
                    (string) ($fields['tags'] ?? '')
                );
            } catch (Throwable $exception) {
                return $this->failure(
                    'BATCH CREATE TAG ERROR = ' . $exception->getMessage()
                );
            }

            $data = new ProductDraftData(
                $baseTitle,
                trim((string) ($fields['slug'] ?? '')),
                $itemId,
                $version,
                $sourceUpdateDate,
                $developer,
                trim((string) ($fields['price'] ?? '249')),
                $salesPage,
                $skuFilename,
                $downloadUrl,
                (int) ($fields['featured_image_id'] ?? 0),
                $tags,
                $content['ruShort'],
                $content['ruLong'],
                $content['ruMeta'],
                $content['enShort'],
                $content['enLong'],
                $content['enMeta'],
                'Created from WP Shop Builder Import Queue. Review before publish.',
                false,
                false
            );
            $preflight = $controller->preflightDraft($data);

            if (! $preflight->success) {
                return new ProductDraftResult(
                    false,
                    null,
                    array_merge(
                        [
                            'BATCH CREATE = RECEIVED',
                            'BATCH ZIP = ' . $filename,
                            'BATCH ACTION = CREATE DRAFT',
                        ],
                        $autofill->logs,
                        $preflight->logs,
                        ['BATCH CREATE = PREFLIGHT FAILED']
                    )
                );
            }

            if (! (bool) ($this->call)('wp_mkdir_p', $targetDir)) {
                return $this->failure(
                    'BATCH CREATE = TARGET DIRECTORY CREATE FAILED'
                );
            }

            if (! (bool) ($this->call)('copy', $sourcePath, $targetPath)) {
                return $this->failure('BATCH CREATE = ZIP COPY FAILED');
            }

            $result = $controller->createDraft($data);

            if (! $result->success) {
                if ((bool) ($this->call)('file_exists', $targetPath)) {
                    ($this->call)('unlink', $targetPath);
                }

                return new ProductDraftResult(
                    false,
                    null,
                    array_merge(
                        [
                            'BATCH CREATE = RECEIVED',
                            'BATCH ZIP = ' . $filename,
                            'BATCH ACTION = CREATE DRAFT',
                            'BATCH ROLLBACK TARGET ARCHIVE = READY',
                        ],
                        $autofill->logs,
                        $preflight->logs,
                        $result->logs,
                        ['BATCH CREATE = PRODUCT DRAFT FAILED']
                    )
                );
            }

            $cleanup = (bool) ($this->call)('unlink', $sourcePath)
                ? 'BATCH INBOX SOURCE = REMOVED'
                : 'BATCH INBOX SOURCE = CLEANUP FAILED';

            return new ProductDraftResult(
                true,
                $result->productId,
                array_merge(
                    [
                        'BATCH CREATE = RECEIVED',
                        'BATCH ZIP = ' . $filename,
                        'BATCH ACTION = CREATE DRAFT',
                        'ENVATO ITEM ID = ' . $itemId,
                        'PRODUCT TYPE = ' . $envatoType,
                        'ZIP VERSION = SOURCE OF TRUTH: '
                            . ($version !== '' ? $version : '[versionless]'),
                        'ENVATO UPDATE DATE = ' . $sourceUpdateDate,
                        'ARCHIVE CANONICAL NAME = ' . $skuFilename,
                        'ARCHIVE STORAGE = ' . $storagePath,
                        'ARCHIVE ITEM DIRECTORY = ' . $itemId,
                        'DOWNLOAD URL = ' . $downloadUrl,
                        'EDITORIAL CONTENT = AUTO-DRAFT; REVIEW REQUIRED',
                        'ADVANCED LABELS = NONE',
                    ],
                    $autofill->logs,
                    $preflight->logs,
                    $result->logs,
                    [
                        $cleanup,
                        'BATCH CREATE DRAFT = READY',
                        'REVIEW PRODUCT BEFORE PUBLISH',
                    ]
                )
            );
        } catch (Throwable $exception) {
            return $this->failure(
                'BATCH CREATE EXCEPTION = ' . $exception->getMessage()
            );
        }
    }

    /**
     * @return array{
     *   filename: string,
     *   relativePath: string,
     *   itemId: int,
     *   productId: int,
     *   productTitle: string,
     *   productType: string,
     *   currentVersion: string,
     *   detectedVersion: string,
     *   action: string,
     *   status: string,
     *   note: string
     * }|null
     */
    private function rowByFilename(
        string $uploadsBaseDir,
        string $folder,
        string $filename
    ): ?array {
        foreach ($this->scanner->scan($uploadsBaseDir, $folder) as $row) {
            if ($row['filename'] === $filename) {
                return $row;
            }
        }

        return null;
    }

    private function resolveItemReference(
        string $reference,
        int $rowItemId,
        string $productType
    ): string {
        $reference = trim($reference);

        if ($reference === '' && $rowItemId > 0) {
            return $this->syntheticItemUrl($rowItemId, $productType);
        }

        if (preg_match('/^\d+$/', $reference) === 1) {
            return $this->syntheticItemUrl((int) $reference, $productType);
        }

        return $reference;
    }

    private function syntheticItemUrl(int $itemId, string $productType): string
    {
        if ($itemId <= 0) {
            return '';
        }

        $host = $productType === CatalogProductType::PLUGIN
            ? 'https://codecanyon.net/item/product/'
            : 'https://themeforest.net/item/product/';

        return $host . $itemId;
    }

    private function sourcePath(
        string $uploadsBaseDir,
        string $folder,
        string $filename
    ): string {
        return $this->scanner->inboxDirectory($uploadsBaseDir)
            . ($folder !== '' ? DIRECTORY_SEPARATOR . $folder : '')
            . DIRECTORY_SEPARATOR . $filename;
    }

    private function normalizeFolder(string $folder): string
    {
        $folder = trim($folder);

        if ($folder === '') {
            return '';
        }

        if (
            $folder === '.'
            || $folder === '..'
            || basename($folder) !== $folder
            || preg_match('/^[A-Za-z0-9._ -]+$/', $folder) !== 1
        ) {
            throw new RuntimeException('Unsafe INBOX folder name.');
        }

        return $folder;
    }

    private function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if (
            $filename === ''
            || basename($filename) !== $filename
            || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            throw new RuntimeException('Unsafe ZIP filename.');
        }

        return $filename;
    }

    private function productIdByItemId(int $itemId): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_key' => '_wp_shop_source_item_id',
                'meta_value' => (string) $itemId,
            ]
        );

        return is_array($ids) && $ids !== [] ? (int) reset($ids) : 0;
    }

    private function token(): string
    {
        if (defined('WP_SHOP_ENVATO_TOKEN')) {
            $configured = constant('WP_SHOP_ENVATO_TOKEN');

            if (is_string($configured) && trim($configured) !== '') {
                return trim($configured);
            }
        }

        return trim((string) ($this->call)(
            'get_option',
            'wp_shop_envato_personal_token',
            ''
        ));
    }

    private function validZipSignature(string $path): bool
    {
        $signature = ($this->call)(
            'file_get_contents',
            $path,
            false,
            null,
            0,
            4
        );

        return is_string($signature)
            && in_array(
                $signature,
                ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
                true
            );
    }

    private function identityMatches(string $identityName, string $envatoTitle): bool
    {
        $tokens = $this->identityTokens($identityName);

        if ($tokens === []) {
            return true;
        }

        $haystack = strtolower((string) preg_replace(
            '/[^a-z0-9]+/i',
            ' ',
            $envatoTitle
        ));

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function identityTokens(string $value): array
    {
        $value = strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', $value));
        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $stop = [
            'wordpress', 'theme', 'plugin', 'elementor', 'template', 'kit',
            'pro', 'the', 'and', 'for', 'with', 'education', 'website',
        ];
        $result = [];

        foreach ($tokens as $token) {
            if (strlen($token) >= 4 && ! in_array($token, $stop, true)) {
                $result[] = $token;
            }
        }

        return array_values(array_unique(array_slice($result, 0, 4)));
    }

    /**
     * @return array{
     *   ruShort: string,
     *   ruLong: string,
     *   ruMeta: string,
     *   enShort: string,
     *   enLong: string,
     *   enMeta: string
     * }
     */
    private function editorialContent(
        string $title,
        string $developer,
        string $productType
    ): array {
        $ruType = match ($productType) {
            CatalogProductType::PLUGIN => 'плагин WordPress',
            CatalogProductType::TEMPLATE_KIT => 'набор шаблонов Elementor',
            default => 'тема WordPress',
        };
        $enType = match ($productType) {
            CatalogProductType::PLUGIN => 'WordPress plugin',
            CatalogProductType::TEMPLATE_KIT => 'Elementor template kit',
            default => 'WordPress theme',
        };
        $ruDeveloper = $developer !== '' ? ' от ' . $developer : '';
        $enDeveloper = $developer !== '' ? ' by ' . $developer : '';
        $safeTitle = $this->text($title);
        $safeDeveloperRu = $this->text($ruDeveloper);
        $safeDeveloperEn = $this->text($enDeveloper);

        return [
            'ruShort' => '<p>' . $safeTitle . ' — ' . $ruType
                . $safeDeveloperRu . '.</p>',
            'ruLong' => '<h2>' . $safeTitle . '</h2><p>'
                . $safeTitle . ' — ' . $ruType . $safeDeveloperRu
                . '. Перед публикацией проверьте описание, требования и совместимость на официальной странице разработчика.</p>',
            'ruMeta' => $title . ' — ' . $ruType . $ruDeveloper
                . '. Актуальная версия и официальный источник.',
            'enShort' => '<p>' . $safeTitle . ' — ' . $enType
                . $safeDeveloperEn . '.</p>',
            'enLong' => '<h2>' . $safeTitle . '</h2><p>'
                . $safeTitle . ' — ' . $enType . $safeDeveloperEn
                . '. Review the description, requirements and compatibility on the official developer page before publishing.</p>',
            'enMeta' => $title . ' — ' . $enType . $enDeveloper
                . '. Current version and official source.',
        ];
    }

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildController(): ProductManagerController
    {
        $transport = new WordPressEnvatoTransport();
        $envato = new EnvatoClient($transport(...), new EnvatoItemMapper());
        $tagRepository = new WordPressCatalogTagRepository();
        $gateway = new WordPressWooCommerceDraftGateway($this->call);
        $creator = new ProductDraftCreator(
            $gateway,
            new ProductDraftValidator(),
            [
                new ProductTaxonomyWriter($this->call),
                new ProductMetadataWriter($this->call),
                new SureRankWriter($this->call),
                new AdvancedLabelWriter($this->call),
            ]
        );

        return new ProductManagerController(
            $envato,
            new ExistingTagSelector($tagRepository),
            $creator,
            null,
            new ExistingCatalogTagParser($tagRepository),
            null
        );
    }

    private function failure(string $message): ProductDraftResult
    {
        return new ProductDraftResult(
            false,
            null,
            [
                'BATCH CREATE = STOPPED',
                $message,
                'NO PRODUCT WRITTEN = YES',
            ]
        );
    }
}
