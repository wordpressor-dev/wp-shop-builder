<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Admin;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Draft\ProductArchiveUploader;
use WPShop\App\Plugin\ProductManager\Draft\ProductDownloadUrl;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftCreator;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftResult;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;
use WPShop\App\Plugin\ProductManager\Draft\ProductVendorSkuFilename;
use WPShop\App\Plugin\ProductManager\ProductSourceType;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Tags\CatalogTag;
use WPShop\App\Plugin\ProductManager\Tags\ExistingCatalogTagParser;
use WPShop\App\Plugin\ProductManager\Tags\ExistingTagSelector;
use WPShop\App\Plugin\ProductManager\Translation\ProductTranslationResult;
use WPShop\App\Plugin\ProductManager\Translation\TranslatePressProductTranslator;

final class ProductManagerController
{
    public function __construct(
        private readonly EnvatoClientInterface $envato,
        private readonly ExistingTagSelector $tags,
        private readonly ?ProductDraftCreator $draftCreator = null,
        private readonly ?TranslatePressProductTranslator $translator = null,
        private readonly ?ExistingCatalogTagParser $tagParser = null,
        private readonly ?ProductArchiveUploader $archiveUploader = null
    ) {
    }

    public function autofill(
        string $itemUrl,
        string $token
    ): ProductManagerAutofillResult {
        try {
            $item = $this->envato->fetch(
                trim($itemUrl),
                trim($token)
            );
        } catch (Throwable $exception) {
            return new ProductManagerAutofillResult(
                false,
                [],
                [
                    'ENVATO AUTOFILL FAILED.',
                    'ERROR TYPE: ' . $exception::class,
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        $selectedTags = $this->tags->select($item->source);
        $productType = CatalogProductType::infer(
            $item->baseTitle,
            $item->salesPage
        );
        [$featuredImageId, $featuredImageLogs] =
            $this->importEnvatoPreview(
                $item->previewImageUrl,
                $item->baseTitle
            );
        [$downloadUrl, $downloadUrlLogs] = $this->suggestDownloadUrl(
            $productType,
            $item->itemId,
            $item->skuFilename
        );

        $fields = [
            'base_title' => $item->baseTitle,
            'slug' => $item->productSlug,
            'item_id' => (string) $item->itemId,
            'version' => $item->version,
            'source_update_date' => $item->updatedDate,
            'developer' => $item->developer,
            'price' => '249',
            'sales_page' => $item->salesPage,
            'sku_filename' => $item->skuFilename,
            'download_url' => $downloadUrl,
            'featured_image_id' => $featuredImageId > 0
                ? (string) $featuredImageId
                : '',
            'featured_image_source_url' => $item->previewImageUrl,
            'tags' => $this->tagLines($selectedTags),
        ];

        $versionLogs = $this->versionLogs(
            $productType,
            $item->version,
            'ENVATO VERSION'
        );

        return new ProductManagerAutofillResult(
            true,
            $fields,
            array_merge(
                [
                    'ENVATO AUTOFILL = READY',
                    'ITEM ID = ' . $item->itemId,
                    'PRODUCT TYPE = ' . $productType,
                ],
                $versionLogs,
                [
                    'DEVELOPER = ' . (
                        $item->developer !== ''
                            ? $item->developer
                            : 'REVIEW_REQUIRED'
                    ),
                    'FEATURED IMAGE SOURCE = ' . (
                        $item->previewImageUrl !== ''
                            ? 'ENVATO PREVIEW READY'
                            : 'NOT PROVIDED'
                    ),
                ],
                $featuredImageLogs,
                $downloadUrlLogs,
                [
                    'EXISTING TAGS SUGGESTED = '
                        . count($selectedTags),
                    'EDITORIAL CONTENT = MANUAL',
                ]
            )
        );
    }

    /**
     * @return array{string, list<string>}
     */
    private function suggestDownloadUrl(
        string $productType,
        int $itemId,
        string $skuFilename
    ): array {
        if ($skuFilename === '') {
            return [
                '',
                [
                    'DOWNLOAD URL AUTO-FILL = NOT AVAILABLE',
                    'ARCHIVE UPLOAD = REQUIRED AT CREATE OR MANUAL URL',
                ],
            ];
        }

        if (! $this->wpFunctionAvailable('wp_upload_dir')) {
            return [
                '',
                [
                    'DOWNLOAD URL AUTO-FILL = UNAVAILABLE',
                    'ARCHIVE UPLOAD = REQUIRED AT CREATE OR MANUAL URL',
                ],
            ];
        }

        try {
            $uploads = $this->wpCall('wp_upload_dir');

            if (! is_array($uploads)) {
                throw new RuntimeException(
                    'WordPress uploads configuration is unavailable.'
                );
            }

            $error = trim((string) ($uploads['error'] ?? ''));

            if ($error !== '') {
                throw new RuntimeException($error);
            }

            $downloadUrl = ProductDownloadUrl::build(
                (string) ($uploads['baseurl'] ?? ''),
                $productType,
                $itemId,
                $skuFilename
            );

            if ($downloadUrl === '') {
                throw new RuntimeException(
                    'Canonical download URL could not be built.'
                );
            }

            return [
                $downloadUrl,
                [
                    'DOWNLOAD URL AUTO-FILL = READY',
                    'DOWNLOAD STORAGE = '
                        . CatalogProductType::storageFolder($productType),
                    'ARCHIVE UPLOAD = SELECT ZIP BEFORE CREATE',
                ],
            ];
        } catch (Throwable $exception) {
            return [
                '',
                [
                    'DOWNLOAD URL AUTO-FILL = FAILED',
                    'DOWNLOAD URL ERROR = ' . $exception->getMessage(),
                    'ARCHIVE UPLOAD = REQUIRED AT CREATE OR MANUAL URL',
                ],
            ];
        }
    }

    /**
     * @return array{int, list<string>}
     */
    private function importEnvatoPreview(
        string $sourceUrl,
        string $title
    ): array {
        $sourceUrl = trim($sourceUrl);

        if ($sourceUrl === '') {
            return [0, ['FEATURED IMAGE AUTO-IMPORT = NOT AVAILABLE']];
        }

        try {
            $existingId = $this->existingAttachmentForSource($sourceUrl);

            if ($existingId > 0) {
                return [
                    $existingId,
                    [
                        'FEATURED IMAGE AUTO-IMPORT = REUSED',
                        'FEATURED IMAGE ATTACHMENT ID = ' . $existingId,
                    ],
                ];
            }

            $this->ensureMediaSideloadFunctions();

            if (! $this->wpFunctionAvailable('media_sideload_image')) {
                return [
                    0,
                    [
                        'FEATURED IMAGE AUTO-IMPORT = UNAVAILABLE',
                        'FEATURED IMAGE FALLBACK = MANUAL PICKER',
                    ],
                ];
            }

            $attachmentId = $this->wpCall(
                'media_sideload_image',
                $sourceUrl,
                0,
                $title,
                'id'
            );

            if (
                $this->wpFunctionAvailable('is_wp_error')
                && (bool) $this->wpCall('is_wp_error', $attachmentId)
            ) {
                $message = is_object($attachmentId)
                    && method_exists($attachmentId, 'get_error_message')
                        ? (string) $attachmentId->get_error_message()
                        : 'Unknown WordPress media error.';

                return [
                    0,
                    [
                        'FEATURED IMAGE AUTO-IMPORT = FAILED',
                        'FEATURED IMAGE ERROR = ' . $message,
                        'FEATURED IMAGE FALLBACK = MANUAL PICKER',
                    ],
                ];
            }

            $attachmentId = (int) $attachmentId;

            if ($attachmentId <= 0) {
                return [
                    0,
                    [
                        'FEATURED IMAGE AUTO-IMPORT = FAILED',
                        'FEATURED IMAGE FALLBACK = MANUAL PICKER',
                    ],
                ];
            }

            return [
                $attachmentId,
                [
                    'FEATURED IMAGE AUTO-IMPORT = READY',
                    'FEATURED IMAGE ATTACHMENT ID = ' . $attachmentId,
                ],
            ];
        } catch (Throwable $exception) {
            return [
                0,
                [
                    'FEATURED IMAGE AUTO-IMPORT = FAILED',
                    'FEATURED IMAGE ERROR = ' . $exception->getMessage(),
                    'FEATURED IMAGE FALLBACK = MANUAL PICKER',
                ],
            ];
        }
    }

    private function existingAttachmentForSource(string $sourceUrl): int
    {
        if (! $this->wpFunctionAvailable('get_posts')) {
            return 0;
        }

        $ids = $this->wpCall(
            'get_posts',
            [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => '_source_url',
                'meta_value' => $sourceUrl,
                'no_found_rows' => true,
            ]
        );

        if (! is_array($ids) || $ids === []) {
            return 0;
        }

        return max(0, (int) $ids[0]);
    }

    private function ensureMediaSideloadFunctions(): void
    {
        if ($this->wpFunctionAvailable('media_sideload_image')) {
            return;
        }

        if (! defined('ABSPATH')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    private function wpFunctionAvailable(string $name): bool
    {
        return is_callable($name);
    }

    private function wpCall(string $name, mixed ...$arguments): mixed
    {
        if (! is_callable($name)) {
            throw new RuntimeException(
                'WordPress function is unavailable: ' . $name
            );
        }

        return Closure::fromCallable($name)(...$arguments);
    }

    /**
     * @return list<CatalogTag>
     */
    public function parseExistingTags(string $value): array
    {
        if ($this->tagParser === null) {
            if (trim($value) === '') {
                return [];
            }

            throw new InvalidArgumentException(
                'Catalog tag parser is unavailable.'
            );
        }

        return $this->tagParser->parse($value);
    }

    public function preflightDraft(
        ProductDraftData $data
    ): ProductDraftResult {
        if ($this->draftCreator === null) {
            return new ProductDraftResult(
                false,
                null,
                ['DRAFT_CREATOR_UNAVAILABLE']
            );
        }

        $prepared = $this->prepareIdentity(
            $data,
            'PREFLIGHT REQUEST = RECEIVED'
        );

        if ($prepared instanceof ProductDraftResult) {
            return $prepared;
        }

        [$preparedData, $identityLogs] = $prepared;
        $result = $this->draftCreator->preflight($preparedData);

        return new ProductDraftResult(
            $result->success,
            null,
            array_merge($identityLogs, $result->logs)
        );
    }

    /**
     * @param array<string, mixed> $archiveFile
     */
    public function createDraft(
        ProductDraftData $data,
        array $archiveFile = []
    ): ProductDraftResult {
        if ($this->draftCreator === null) {
            return new ProductDraftResult(
                false,
                null,
                ['DRAFT_CREATOR_UNAVAILABLE']
            );
        }

        $prepared = $this->prepareIdentity(
            $data,
            'CREATE REQUEST = RECEIVED'
        );

        if ($prepared instanceof ProductDraftResult) {
            return $prepared;
        }

        [$preparedData, $identityLogs] = $prepared;
        $archiveLogs = [];
        $archiveResult = null;

        if ($this->archiveUploader !== null) {
            $archiveResult = $this->archiveUploader->storeForCreate(
                $archiveFile,
                $preparedData->baseTitle,
                $preparedData->salesPage,
                $preparedData->itemId,
                $preparedData->version
            );
            $archiveLogs = $archiveResult->logs;

            if (! $archiveResult->success) {
                return new ProductDraftResult(
                    false,
                    null,
                    array_merge($identityLogs, $archiveLogs)
                );
            }

            if ($archiveResult->supplied) {
                $preparedData = $preparedData->withArchive(
                    $archiveResult->skuFilename,
                    $archiveResult->downloadUrl
                );
            }
        } elseif ($archiveFile !== []) {
            return new ProductDraftResult(
                false,
                null,
                array_merge(
                    $identityLogs,
                    [
                        'ARCHIVE UPLOAD = FAILED',
                        'ARCHIVE UPLOADER = UNAVAILABLE',
                        'PRODUCT WRITE = BLOCKED',
                    ]
                )
            );
        }

        $result = $this->draftCreator->create($preparedData);
        $archiveFinishLogs = [];

        if (
            $archiveResult !== null
            && $archiveResult->supplied
        ) {
            $archiveFinishLogs = $result->success
                ? $this->archiveUploader->finalize($archiveResult)
                : $this->archiveUploader->rollback($archiveResult);
        }

        return new ProductDraftResult(
            $result->success,
            $result->productId,
            array_merge(
                $identityLogs,
                $archiveLogs,
                $result->logs,
                $archiveFinishLogs
            )
        );
    }

    public function translate(
        int $productId,
        string $enShort,
        string $enLong,
        string $enMeta
    ): ProductTranslationResult {
        if ($this->translator === null) {
            return new ProductTranslationResult(
                false,
                ['TRANSLATOR_UNAVAILABLE']
            );
        }

        return $this->translator->translate(
            $productId,
            $enShort,
            $enLong,
            $enMeta
        );
    }

    /**
     * @return array{ProductDraftData, list<string>}|ProductDraftResult
     */
    private function prepareIdentity(
        ProductDraftData $data,
        string $requestLog
    ): array|ProductDraftResult {
        $sourceType = ProductSourceType::fromSalesPage(
            $data->salesPage
        );

        try {
            $canonicalSku = $sourceType === ProductSourceType::VENDOR
                ? ProductVendorSkuFilename::synchronize(
                    $data->skuFilename,
                    $data->version,
                    $data->version
                )
                : ProductSkuFilename::synchronize(
                    $data->skuFilename,
                    $data->itemId,
                    $data->salesPage,
                    $data->version
                );
        } catch (InvalidArgumentException $exception) {
            return new ProductDraftResult(
                false,
                null,
                [
                    $requestLog,
                    'STOP: DRAFT NOT CREATED.',
                    'VERSION / SKU SAFETY CHECK = FAILED',
                    'SOURCE TYPE = ' . strtoupper($sourceType),
                    'ERROR MESSAGE: ' . $exception->getMessage(),
                ]
            );
        }

        $productType = CatalogProductType::infer(
            $data->baseTitle,
            $data->salesPage
        );
        $logs = array_merge(
            [
                $requestLog,
                'SOURCE TYPE = ' . strtoupper($sourceType),
            ],
            $this->versionLogs(
                $productType,
                $data->version,
                'MANUAL VERSION'
            )
        );

        if ($canonicalSku !== $data->skuFilename) {
            $logs[] = 'SKU AUTO-SYNC: '
                . ($data->skuFilename !== ''
                    ? $data->skuFilename
                    : '[empty]')
                . ' -> '
                . $canonicalSku;
        } else {
            $logs[] = $sourceType === ProductSourceType::VENDOR
                ? 'VENDOR SKU / VERSION = MATCH'
                : (
                    $data->version !== ''
                        ? 'SKU / VERSION = MATCH'
                        : 'SKU / VERSIONLESS MODE = MATCH'
                );
        }

        return [
            $data->withSkuFilename($canonicalSku),
            $logs,
        ];
    }

    /**
     * @return list<string>
     */
    private function versionLogs(
        string $productType,
        string $version,
        string $label
    ): array {
        $version = trim($version);

        if (
            $productType === CatalogProductType::TEMPLATE_KIT
            && $version === ''
        ) {
            return [
                'VERSION MODE = VERSIONLESS TEMPLATE KIT',
                'PUBLISHED VERSION = NOT PROVIDED',
                'VERSION FIELD = OPTIONAL',
            ];
        }

        if ($version === '') {
            return [
                $label . ' = REVIEW_REQUIRED',
                'VERSION CHECK = MANUAL REQUIRED BEFORE DRAFT',
            ];
        }

        return [
            $label . ' = SOURCE OF TRUTH: ' . $version,
        ];
    }

    /**
     * @param list<CatalogTag> $tags
     */
    private function tagLines(array $tags): string
    {
        $lines = [];

        foreach ($tags as $tag) {
            $lines[] = $tag->name . '|' . $tag->slug;
        }

        return implode("\n", $lines);
    }
}
