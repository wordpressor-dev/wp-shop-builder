<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Translation\ProductTranslationResult;
use WPShop\App\Plugin\ProductManager\Translation\TranslationMapBuilder;

final class ProductEditorialMigrationService
{
    private const BACKUP_META = '_wp_shop_editorial_backup_v28';
    private const STANDARD_META = '_wp_shop_editorial_standard';
    private const MIGRATED_AT_META = '_wp_shop_editorial_migrated_at';
    private const EN_TARGET_RU_FINGERPRINT_META = '_wp_shop_en_target_ru_fingerprint_v2';
    private const EN_CONTENT_FINGERPRINT_META = '_wp_shop_en_content_fingerprint_v2';
    private const MANUAL_DRAFT_META = '_wp_shop_editorial_manual_draft_v1';
    private const TYPE_OVERRIDE_BACKUP_META = '_wp_shop_product_type_manual_backup_v1';
    private const TYPE_OVERRIDE_META = '_wp_shop_product_type_manual_override_v1';

    public function __construct(
        private readonly Closure $call,
        private readonly ?EnvatoClientInterface $envato = null,
        private readonly ProductEditorialDraftBuilder $builder = new ProductEditorialDraftBuilder(),
        private readonly EnvatoOfficialFactsExtractor $officialExtractor = new EnvatoOfficialFactsExtractor(),
        private readonly ?Closure $translate = null
    ) {
    }

    /**
     * @return array{
     * productId:int,title:string,baseTitle:string,status:string,productType:string,
     * developer:string,sourceUpdateDate:string,ruStatus:string,enStatus:string,
     * metaStatus:string,backupAvailable:bool,officialStatus:string,officialFacts:int,
     * current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     * generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * }
     */
    public function preview(int $productId): array
    {
        $post = ($this->call)('get_post', $productId);
        $row = is_object($post) ? get_object_vars($post) : [];
        if (($row['post_type'] ?? '') !== 'product') {
            throw new RuntimeException('Product not found: ' . $productId);
        }

        $title = trim((string) ($row['post_title'] ?? ''));
        $baseTitle = $this->baseTitle($productId, $title);
        $current = [
            'ruShort' => (string) ($row['post_excerpt'] ?? ''),
            'ruLong' => (string) ($row['post_content'] ?? ''),
            'ruMeta' => $this->sureRankMeta($productId),
            'enShort' => $this->meta($productId, '_wp_shop_en_short_description'),
            'enLong' => $this->meta($productId, '_wp_shop_en_long_description'),
            'enMeta' => $this->meta($productId, '_wp_shop_en_meta_description'),
        ];

        $productType = $this->productType(
            $productId,
            $baseTitle,
            implode(' ', [
                $current['ruShort'],
                $current['ruLong'],
                $current['ruMeta'],
                $current['enShort'],
                $current['enLong'],
                $current['enMeta'],
            ])
        );
        $developer = $this->meta($productId, 'attr_developer_value');
        $sourceUpdateDate = $this->meta($productId, '_wp_shop_source_update_date');

        if ($productType === '') {
            return [
                'productId' => $productId,
                'title' => $title,
                'baseTitle' => $baseTitle,
                'status' => 'STOP',
                'productType' => 'unknown',
                'developer' => $developer,
                'sourceUpdateDate' => $sourceUpdateDate,
                'ruStatus' => 'REVIEW',
                'enStatus' => 'REVIEW',
                'metaStatus' => 'REVIEW',
                'backupAvailable' => false,
                'officialStatus' => 'TYPE UNKNOWN',
                'officialFacts' => 0,
                'current' => $current,
                'generated' => $current,
            ];
        }

        $backup = ($this->call)('get_post_meta', $productId, self::BACKUP_META, true);
        if (
            $this->meta($productId, self::STANDARD_META) === 'v28-manual'
            && $this->manualContentIssue($current) === ''
        ) {
            return [
                'productId' => $productId,
                'title' => $title,
                'baseTitle' => $baseTitle,
                'status' => 'CURRENT',
                'productType' => $productType,
                'developer' => $developer,
                'sourceUpdateDate' => $sourceUpdateDate,
                'ruStatus' => 'CURRENT',
                'enStatus' => 'CURRENT',
                'metaStatus' => 'CURRENT',
                'backupAvailable' => is_array($backup) && $backup !== [],
                'officialStatus' => 'MANUAL EDITORIAL',
                'officialFacts' => 0,
                'current' => $current,
                'generated' => $current,
            ];
        }

        $signals = $this->signals($productId, $baseTitle);
        $official = $this->officialFacts($productId);
        $signals = str_starts_with($official['status'], 'READY')
            ? array_values(array_unique(array_merge($this->semanticSignals($signals), $official['signals'])))
            : array_values(array_unique(array_merge($signals, $official['signals'])));

        $generic = $this->builder->build(
            $baseTitle,
            $developer,
            $productType,
            $signals,
            $sourceUpdateDate
        );
        $generated = $generic;
        $legacy = null;

        if (is_array($backup) && $backup !== []) {
            $legacy = $backup;

            // Keep the original RU backup immutable, but let a freshly imported
            // EN Pack v2 override stale EN that was captured in the backup.
            foreach (['enShort', 'enLong', 'enMeta'] as $field) {
                if (trim($current[$field]) !== '') {
                    $legacy[$field] = $current[$field];
                }
            }
        } elseif (! $this->sameList(array_values($current), array_values($generic))) {
            $legacy = $current;
        }

        if (is_array($legacy)) {
            $generated = $this->builder->build(
                $baseTitle,
                $developer,
                $productType,
                $signals,
                $sourceUpdateDate,
                $this->enrichLegacy($legacy, $official, $generic)
            );
            $generated = $this->preserveQualityMeta($legacy, $generated, $productType);
            $generated = $this->preserveRichLegacyRu($legacy, $generated, $productType);
        }

        $stalePreparedEnglish = false;
        $preparedTargetFingerprint = $this->meta($productId, self::EN_TARGET_RU_FINGERPRINT_META);
        $preparedEnglishFingerprint = $this->meta($productId, self::EN_CONTENT_FINGERPRINT_META);
        $preparedEnglishIsCurrent = $preparedTargetFingerprint !== ''
            && $preparedEnglishFingerprint !== ''
            && hash_equals($preparedEnglishFingerprint, $this->englishFingerprint($current));

        if (
            $preparedEnglishIsCurrent
            && ! hash_equals($preparedTargetFingerprint, $this->ruFingerprint($generated))
        ) {
            $stalePreparedEnglish = true;
            if (is_array($legacy)) {
                $legacyWithoutEnglish = $legacy;
                foreach (['enShort', 'enLong', 'enMeta'] as $field) {
                    $legacyWithoutEnglish[$field] = '';
                }
                $generated = $this->builder->build(
                    $baseTitle,
                    $developer,
                    $productType,
                    $signals,
                    $sourceUpdateDate,
                    $this->enrichLegacy($legacyWithoutEnglish, $official, $generic)
                );
                $generated = $this->preserveQualityMeta($legacyWithoutEnglish, $generated, $productType);
                $generated = $this->preserveRichLegacyRu($legacyWithoutEnglish, $generated, $productType);
            }
        }

        $translationIssue = $this->translationIssue($generated);
        $translationRequired = (is_array($legacy)
            && $this->requiresEnglishTranslation($legacy))
            || $translationIssue !== '';
        $ruStatus = $this->status(
            [$current['ruShort'], $current['ruLong']],
            [$generated['ruShort'], $generated['ruLong']]
        );
        $enStatus = $translationRequired
            ? 'REVIEW'
            : $this->status(
                [$current['enShort'], $current['enLong'], $current['enMeta']],
                [$generated['enShort'], $generated['enLong'], $generated['enMeta']]
            );
        $metaStatus = $this->singleStatus($current['ruMeta'], $generated['ruMeta']);
        $status = $translationRequired
            ? 'STOP'
            : ($ruStatus === 'CURRENT' && $enStatus === 'CURRENT' && $metaStatus === 'CURRENT'
                ? 'CURRENT'
                : 'MIGRATE');

        return [
            'productId' => $productId,
            'title' => $title,
            'baseTitle' => $baseTitle,
            'status' => $status,
            'productType' => $productType,
            'developer' => $developer,
            'sourceUpdateDate' => $sourceUpdateDate,
            'ruStatus' => $ruStatus,
            'enStatus' => $enStatus,
            'metaStatus' => $metaStatus,
            'backupAvailable' => is_array($backup) && $backup !== [],
            'officialStatus' => $official['status'],
            'officialFacts' => count($official['ruFacts']),
            'current' => $current,
            'generated' => $generated,
        ];
    }

    /** @return list<string> */
    public function apply(int $productId): array
    {
        $preview = $this->preview($productId);
        $translationIssue = $this->translationIssue($preview['generated']);

        if ($preview['status'] === 'STOP') {
            if ($preview['productType'] === 'unknown') {
                $reason = 'product type is unknown';
            } elseif ($translationIssue !== '') {
                $reason = 'English editorial translation is structurally incompatible: '
                    . $translationIssue;
            } else {
                $reason = 'English editorial translation is incomplete';
            }

            throw new RuntimeException(
                'Editorial migration stopped: ' . $reason . ' for product ' . $productId
            );
        }

        $this->assertTranslationCompatible($productId, $preview['generated']);

        if ($preview['status'] === 'CURRENT') {
            $logs = [
                'EDITORIAL MIGRATION = NO CHANGE',
                'PRODUCT ID = ' . $productId,
            ];
            foreach ($this->syncTranslatePress($productId, $preview['generated']) as $line) {
                $logs[] = $line;
            }
            $logs[] = 'EDITORIAL STANDARD = CURRENT';
            return $logs;
        }

        $backup = ($this->call)('get_post_meta', $productId, self::BACKUP_META, true);
        $backupLog = 'EDITORIAL BACKUP = REUSED';
        if (! is_array($backup) || $backup === []) {
            $backup = array_merge(['created_at' => $this->now()], $preview['current']);
            ($this->call)('update_post_meta', $productId, self::BACKUP_META, $backup);
            $backupLog = 'EDITORIAL BACKUP = CREATED';
        }

        $this->writeContent($productId, $preview['generated']);
        ($this->call)('update_post_meta', $productId, self::STANDARD_META, 'v28');
        ($this->call)('update_post_meta', $productId, self::MIGRATED_AT_META, $this->now());

        $logs = [
            'EDITORIAL MIGRATION = READY',
            'PRODUCT ID = ' . $productId,
            $backupLog,
            'OFFICIAL ENVATO ENRICHMENT = ' . $preview['officialStatus'],
            'OFFICIAL FACTS USED = ' . $preview['officialFacts'],
            'RU SHORT / LONG = UPDATED',
            'SURERANK META DESCRIPTION = UPDATED',
            'EN SHORT / LONG / META = PREPARED',
        ];

        foreach ($this->syncTranslatePress($productId, $preview['generated']) as $line) {
            $logs[] = $line;
        }
        $logs[] = 'EDITORIAL STANDARD = v28';
        return $logs;
    }

    /**
     * @param array<string,string> $content
     * @return list<string>
     */
    public function saveManualDraft(int $productId, array $content): array
    {
        $preview = $this->preview($productId);
        if ($preview['productType'] === 'unknown') {
            throw new RuntimeException(
                'Manual editorial draft stopped: technical product type is unknown for product '
                . $productId
            );
        }

        $safe = $this->sanitizeManualContent($content);
        $issue = $this->manualContentIssue($safe);
        if ($issue !== '') {
            throw new RuntimeException('Manual editorial draft stopped: ' . $issue);
        }

        ($this->call)('update_post_meta', $productId, self::MANUAL_DRAFT_META, [
            'version' => '1',
            'saved_at' => $this->now(),
            'product_type' => $preview['productType'],
            'source_fingerprint' => $this->contentFingerprint($preview['current']),
            'content' => $safe,
        ]);

        return [
            'MANUAL EDITORIAL DRAFT = SAVED',
            'PRODUCT ID = ' . $productId,
            'TECHNICAL TYPE = ' . $preview['productType'],
            'RU / EN STRUCTURE = READY',
            'PRODUCT CONTENT WRITES = NO',
            'NEXT = REVIEW MANUAL PREVIEW, THEN APPLY MANUAL',
        ];
    }

    /**
     * @return array{
     * productId:int,title:string,productType:string,developer:string,
     * status:string,issue:string,hasDraft:bool,sourceCurrent:bool,backupAvailable:bool,
     * current:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     * generated:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string},
     * draft:array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     * }
     */
    public function manualEditor(int $productId): array
    {
        $preview = $this->preview($productId);
        $stored = ($this->call)('get_post_meta', $productId, self::MANUAL_DRAFT_META, true);
        $hasDraft = is_array($stored)
            && ($stored['version'] ?? '') === '1'
            && is_array($stored['content'] ?? null);
        $draft = $hasDraft
            ? $this->sanitizeManualContent($stored['content'])
            : $preview['current'];
        $sourceFingerprint = $hasDraft
            ? trim((string) ($stored['source_fingerprint'] ?? ''))
            : '';
        $sourceCurrent = ! $hasDraft
            || ($sourceFingerprint !== ''
                && hash_equals($sourceFingerprint, $this->contentFingerprint($preview['current'])));

        $issue = '';
        if ($preview['productType'] === 'unknown') {
            $issue = 'Technical product type is unknown.';
        } elseif ($hasDraft && (string) ($stored['product_type'] ?? '') !== $preview['productType']) {
            $issue = 'Technical product type changed after the manual draft was saved.';
        } elseif ($hasDraft && ! $sourceCurrent) {
            $issue = 'Current product content changed after the manual draft was saved. Reload and save the draft again.';
        } elseif ($hasDraft) {
            $issue = $this->manualContentIssue($draft);
        }

        return [
            'productId' => $productId,
            'title' => (string) $preview['title'],
            'productType' => (string) $preview['productType'],
            'developer' => (string) $preview['developer'],
            'status' => ! $hasDraft ? 'EMPTY' : ($issue === '' ? 'READY' : 'REVIEW'),
            'issue' => $issue,
            'hasDraft' => $hasDraft,
            'sourceCurrent' => $sourceCurrent,
            'backupAvailable' => ! empty($preview['backupAvailable']),
            'current' => $preview['current'],
            'generated' => $preview['generated'],
            'draft' => $draft,
        ];
    }

    /** @return list<string> */
    public function applyManual(int $productId): array
    {
        $editor = $this->manualEditor($productId);
        if ($editor['status'] !== 'READY') {
            $reason = $editor['issue'] !== '' ? $editor['issue'] : 'manual draft is not ready';
            throw new RuntimeException(
                'Manual editorial apply stopped before write for product '
                . $productId . ': ' . $reason
            );
        }

        $content = $editor['draft'];
        $this->assertTranslationCompatible($productId, $content);

        $backup = ($this->call)('get_post_meta', $productId, self::BACKUP_META, true);
        $backupLog = 'EDITORIAL BACKUP = REUSED';
        if (! is_array($backup) || $backup === []) {
            $backup = array_merge(['created_at' => $this->now()], $editor['current']);
            ($this->call)('update_post_meta', $productId, self::BACKUP_META, $backup);
            $backupLog = 'EDITORIAL BACKUP = CREATED';
        }

        $this->writeContent($productId, $content);
        ($this->call)('update_post_meta', $productId, self::STANDARD_META, 'v28-manual');
        ($this->call)('update_post_meta', $productId, self::MIGRATED_AT_META, $this->now());
        ($this->call)(
            'update_post_meta',
            $productId,
            self::EN_TARGET_RU_FINGERPRINT_META,
            $this->ruFingerprint($content)
        );
        ($this->call)(
            'update_post_meta',
            $productId,
            self::EN_CONTENT_FINGERPRINT_META,
            $this->englishFingerprint($content)
        );

        $logs = [
            'MANUAL EDITORIAL = READY',
            'PRODUCT ID = ' . $productId,
            $backupLog,
            'TECHNICAL TYPE = ' . $editor['productType'],
            'RU SHORT / LONG = UPDATED',
            'SURERANK META DESCRIPTION = UPDATED',
            'EN SHORT / LONG / META = UPDATED',
            'EN TARGET PROVENANCE = UPDATED',
        ];
        $syncLogs = $this->syncTranslatePress($productId, $content);
        $logs = array_merge($logs, $syncLogs);

        if (in_array('TRANSLATEPRESS SYNC = READY', $syncLogs, true)) {
            ($this->call)('delete_post_meta', $productId, self::MANUAL_DRAFT_META);
            $logs[] = 'MANUAL DRAFT = CLEARED';
        } else {
            ($this->call)('update_post_meta', $productId, self::MANUAL_DRAFT_META, [
                'version' => '1',
                'saved_at' => $this->now(),
                'product_type' => $editor['productType'],
                'source_fingerprint' => $this->contentFingerprint($content),
                'content' => $content,
            ]);
            $logs[] = 'MANUAL DRAFT = PRESERVED FOR TRANSLATEPRESS RETRY';
        }
        $logs[] = 'EDITORIAL STANDARD = v28-manual';

        return $logs;
    }

    /** @return list<string> */
    public function discardManualDraft(int $productId): array
    {
        ($this->call)('delete_post_meta', $productId, self::MANUAL_DRAFT_META);

        return [
            'MANUAL EDITORIAL DRAFT = DISCARDED',
            'PRODUCT ID = ' . $productId,
            'PRODUCT CONTENT WRITES = NO',
        ];
    }

    /**
     * @return array{
     * productId:int,title:string,resolvedType:string,storedType:string,
     * catalogCategory:string,hasManualDraft:bool,backupAvailable:bool,
     * sourceFingerprint:string
     * }
     */
    public function technicalTypeEditor(int $productId): array
    {
        $preview = $this->preview($productId);
        $storedType = trim($this->meta($productId, '_wp_shop_product_type'));
        $catalogCategory = $this->technicalTypeCatalogSnapshot($productId);
        $manualDraft = ($this->call)('get_post_meta', $productId, self::MANUAL_DRAFT_META, true);
        $backup = ($this->call)('get_post_meta', $productId, self::TYPE_OVERRIDE_BACKUP_META, true);

        $sourceFingerprint = hash('sha256', implode("\0", [
            (string) $productId,
            (string) $preview['title'],
            (string) $preview['productType'],
            $storedType,
            $catalogCategory,
            $this->contentFingerprint($preview['current']),
            $this->meta($productId, 'sales_page'),
            $this->meta($productId, self::TYPE_OVERRIDE_META),
        ]));

        return [
            'productId' => $productId,
            'title' => (string) $preview['title'],
            'resolvedType' => (string) $preview['productType'],
            'storedType' => $storedType,
            'catalogCategory' => $catalogCategory,
            'hasManualDraft' => is_array($manualDraft) && $manualDraft !== [],
            'backupAvailable' => is_array($backup) && $backup !== [],
            'sourceFingerprint' => $sourceFingerprint,
        ];
    }

    /**
     * @return array{
     * productId:int,title:string,fromType:string,toType:string,storedType:string,
     * catalogCategory:string,status:string,issue:string,hasManualDraft:bool,
     * backupAvailable:bool,sourceFingerprint:string
     * }
     */
    public function previewTechnicalTypeOverride(int $productId, string $targetType): array
    {
        $targetType = trim($targetType);
        if (! $this->validTechnicalType($targetType)) {
            throw new RuntimeException('Unsupported technical product type: ' . $targetType);
        }

        $editor = $this->technicalTypeEditor($productId);
        $issue = '';
        if ($editor['hasManualDraft'] && $targetType !== $editor['resolvedType']) {
            $issue = 'Manual RU+EN draft exists. Discard it before changing technical type.';
        }

        return [
            'productId' => $productId,
            'title' => $editor['title'],
            'fromType' => $editor['resolvedType'],
            'toType' => $targetType,
            'storedType' => $editor['storedType'],
            'catalogCategory' => $editor['catalogCategory'],
            'status' => $issue !== ''
                ? 'REVIEW'
                : ($targetType === $editor['resolvedType'] ? 'CURRENT' : 'READY'),
            'issue' => $issue,
            'hasManualDraft' => $editor['hasManualDraft'],
            'backupAvailable' => $editor['backupAvailable'],
            'sourceFingerprint' => $editor['sourceFingerprint'],
        ];
    }

    /** @return list<string> */
    public function applyTechnicalTypeOverride(
        int $productId,
        string $targetType,
        string $sourceFingerprint
    ): array {
        $preview = $this->previewTechnicalTypeOverride($productId, $targetType);
        if ($preview['status'] === 'REVIEW') {
            throw new RuntimeException(
                'Technical type override stopped before write for product '
                . $productId . ': ' . $preview['issue']
            );
        }
        if (
            $sourceFingerprint === ''
            || ! hash_equals($preview['sourceFingerprint'], trim($sourceFingerprint))
        ) {
            throw new RuntimeException(
                'Technical type override stopped before write for product '
                . $productId . ': source changed after Preview. Preview again.'
            );
        }

        if ($preview['status'] === 'CURRENT') {
            return [
                'TECHNICAL TYPE OVERRIDE = NO CHANGE',
                'PRODUCT ID = ' . $productId,
                'TECHNICAL TYPE = ' . $targetType,
                'CATALOG CATEGORY = PRESERVED / NOT WRITTEN',
                'PRODUCT CONTENT WRITES = NO',
            ];
        }

        $backup = ($this->call)(
            'get_post_meta',
            $productId,
            self::TYPE_OVERRIDE_BACKUP_META,
            true
        );
        $backupLog = 'TECHNICAL TYPE BACKUP = REUSED';
        if (! is_array($backup) || $backup === []) {
            ($this->call)('update_post_meta', $productId, self::TYPE_OVERRIDE_BACKUP_META, [
                'version' => '1',
                'created_at' => $this->now(),
                'stored_type' => $preview['storedType'],
                'resolved_type' => $preview['fromType'],
                'manual_override_type' => $this->meta($productId, self::TYPE_OVERRIDE_META),
            ]);
            $backupLog = 'TECHNICAL TYPE BACKUP = CREATED';
        }

        ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $targetType);
        ($this->call)('update_post_meta', $productId, self::TYPE_OVERRIDE_META, $targetType);

        $after = $this->technicalTypeEditor($productId);
        if (
            $after['storedType'] !== $targetType
            || $after['resolvedType'] !== $targetType
        ) {
            throw new RuntimeException(
                'Technical type override verification failed for product ' . $productId
            );
        }

        return [
            'TECHNICAL TYPE OVERRIDE = READY',
            'PRODUCT ID = ' . $productId,
            $backupLog,
            'FROM = ' . $preview['fromType'],
            'TO = ' . $targetType,
            '_wp_shop_product_type = UPDATED',
            'TECHNICAL TYPE MANUAL OVERRIDE = UPDATED',
            'CATALOG CATEGORY = PRESERVED / NOT WRITTEN',
            'PRODUCT CONTENT WRITES = NO',
            'TRANSLATEPRESS SYNC = NOT RUN',
            'NEXT = REVIEW EDITORIAL PREVIEW',
        ];
    }

    /** @return list<string> */
    public function restoreTechnicalTypeOverride(int $productId): array
    {
        $editor = $this->technicalTypeEditor($productId);
        if ($editor['hasManualDraft']) {
            throw new RuntimeException(
                'Technical type restore stopped: discard the Manual RU+EN draft first.'
            );
        }

        $backup = ($this->call)(
            'get_post_meta',
            $productId,
            self::TYPE_OVERRIDE_BACKUP_META,
            true
        );
        if (! is_array($backup) || ($backup['version'] ?? '') !== '1') {
            throw new RuntimeException(
                'Technical type backup not found for product ' . $productId
            );
        }

        $storedType = trim((string) ($backup['stored_type'] ?? ''));
        if ($storedType === '') {
            ($this->call)('delete_post_meta', $productId, '_wp_shop_product_type');
        } else {
            if (! $this->validTechnicalType($storedType)) {
                throw new RuntimeException(
                    'Technical type backup contains an unsupported value for product '
                    . $productId
                );
            }
            ($this->call)('update_post_meta', $productId, '_wp_shop_product_type', $storedType);
        }

        $manualOverride = trim((string) ($backup['manual_override_type'] ?? ''));
        if ($manualOverride === '') {
            ($this->call)('delete_post_meta', $productId, self::TYPE_OVERRIDE_META);
        } else {
            if (! $this->validTechnicalType($manualOverride)) {
                throw new RuntimeException(
                    'Technical type backup contains an unsupported manual override for product '
                    . $productId
                );
            }
            ($this->call)('update_post_meta', $productId, self::TYPE_OVERRIDE_META, $manualOverride);
        }

        $after = $this->technicalTypeEditor($productId);
        $expectedResolved = trim((string) ($backup['resolved_type'] ?? ''));
        if ($expectedResolved !== '' && $after['resolvedType'] !== $expectedResolved) {
            throw new RuntimeException(
                'Technical type restore verification failed for product ' . $productId
            );
        }

        return [
            'TECHNICAL TYPE RESTORE = READY',
            'PRODUCT ID = ' . $productId,
            'STORED TYPE = ' . ($storedType !== '' ? $storedType : 'EMPTY / INFERRED'),
            'RESOLVED TYPE = ' . $after['resolvedType'],
            'CATALOG CATEGORY = PRESERVED / NOT WRITTEN',
            'PRODUCT CONTENT WRITES = NO',
            'BACKUP = PRESERVED',
        ];
    }

    private function validTechnicalType(string $type): bool
    {
        return in_array($type, [
            CatalogProductType::THEME,
            CatalogProductType::PLUGIN,
            CatalogProductType::TEMPLATE_KIT,
        ], true);
    }

    private function technicalTypeCatalogSnapshot(int $productId): string
    {
        $parts = [];
        $attribute = trim($this->meta($productId, 'attr_category_value'));
        if ($attribute !== '') {
            $parts[] = 'attr_category_value: ' . $attribute;
        }

        $terms = ($this->call)(
            'wp_get_post_terms',
            $productId,
            'product_cat',
            ['fields' => 'names']
        );
        if (is_array($terms)) {
            $names = [];
            foreach ($terms as $term) {
                if (is_scalar($term) && trim((string) $term) !== '') {
                    $names[] = trim((string) $term);
                }
            }
            $names = array_values(array_unique($names));
            if ($names !== []) {
                $parts[] = 'product_cat: ' . implode(', ', $names);
            }
        }

        return $parts === [] ? '—' : implode(' | ', $parts);
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function ruFingerprint(array $content): string
    {
        return hash('sha256', $content['ruShort'] . "\0" . $content['ruLong'] . "\0" . $content['ruMeta']);
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function englishFingerprint(array $content): string
    {
        return hash('sha256', $content['enShort'] . "\0" . $content['enLong'] . "\0" . $content['enMeta']);
    }

    /**
     * @param array<string,mixed> $content
     * @return array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     */
    private function sanitizeManualContent(array $content): array
    {
        return [
            'ruShort' => (string) ($this->call)('wp_kses_post', (string) ($content['ruShort'] ?? '')),
            'ruLong' => (string) ($this->call)('wp_kses_post', (string) ($content['ruLong'] ?? '')),
            'ruMeta' => (string) ($this->call)(
                'sanitize_textarea_field',
                (string) ($content['ruMeta'] ?? '')
            ),
            'enShort' => (string) ($this->call)('wp_kses_post', (string) ($content['enShort'] ?? '')),
            'enLong' => (string) ($this->call)('wp_kses_post', (string) ($content['enLong'] ?? '')),
            'enMeta' => (string) ($this->call)(
                'sanitize_textarea_field',
                (string) ($content['enMeta'] ?? '')
            ),
        ];
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function manualContentIssue(array $content): string
    {
        foreach ([
            'ruShort' => 'RU Short',
            'ruLong' => 'RU Long',
            'ruMeta' => 'RU Meta',
            'enShort' => 'EN Short',
            'enLong' => 'EN Long',
            'enMeta' => 'EN Meta',
        ] as $field => $label) {
            if (trim($content[$field]) === '') {
                return $label . ' is empty.';
            }
        }

        foreach (['ruShort', 'ruLong', 'enShort', 'enLong'] as $field) {
            if (preg_match('/<(p|h[1-6])\b[^>]*>\s*<\1\b/i', $content[$field]) === 1) {
                return $field . ' contains nested duplicate block tags.';
            }
        }

        $issue = $this->translationIssue($content);
        return $issue === '' ? '' : 'RU/EN structure mismatch: ' . $issue;
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function contentFingerprint(array $content): string
    {
        return hash('sha256', implode("\0", [
            $content['ruShort'],
            $content['ruLong'],
            $content['ruMeta'],
            $content['enShort'],
            $content['enLong'],
            $content['enMeta'],
        ]));
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     * @return list<string>
     */
    private function syncTranslatePress(int $productId, array $content): array
    {
        if ($this->translate === null) {
            return ['TRANSLATEPRESS SYNC = NOT AVAILABLE'];
        }

        $result = ($this->translate)(
            $productId,
            $content['enShort'],
            $content['enLong'],
            $content['enMeta']
        );

        if (! $result instanceof ProductTranslationResult) {
            return [
                'TRANSLATEPRESS SYNC = REVIEW',
                'TRANSLATEPRESS ERROR = INVALID RESULT',
            ];
        }

        $deferred = false;
        foreach ($result->logs as $line) {
            if (str_starts_with($line, 'PUBLISH_FIRST;')) {
                $deferred = true;
                break;
            }
        }

        $status = $result->success
            ? 'TRANSLATEPRESS SYNC = READY'
            : ($deferred
                ? 'TRANSLATEPRESS SYNC = DEFERRED / PUBLISH FIRST'
                : 'TRANSLATEPRESS SYNC = REVIEW');

        return array_merge([$status], $result->logs);
    }

    /** @return list<string> */
    public function restore(int $productId): array
    {
        $backup = ($this->call)('get_post_meta', $productId, self::BACKUP_META, true);
        if (! is_array($backup) || $backup === []) {
            throw new RuntimeException('Editorial backup not found for product ' . $productId);
        }

        $this->writeContent($productId, [
            'ruShort' => (string) ($backup['ruShort'] ?? ''),
            'ruLong' => (string) ($backup['ruLong'] ?? ''),
            'ruMeta' => (string) ($backup['ruMeta'] ?? ''),
            'enShort' => (string) ($backup['enShort'] ?? ''),
            'enLong' => (string) ($backup['enLong'] ?? ''),
            'enMeta' => (string) ($backup['enMeta'] ?? ''),
        ]);
        ($this->call)('delete_post_meta', $productId, self::STANDARD_META);
        ($this->call)('delete_post_meta', $productId, self::MIGRATED_AT_META);
        ($this->call)('delete_post_meta', $productId, self::EN_TARGET_RU_FINGERPRINT_META);
        ($this->call)('delete_post_meta', $productId, self::EN_CONTENT_FINGERPRINT_META);
        ($this->call)('delete_post_meta', $productId, self::MANUAL_DRAFT_META);

        return [
            'EDITORIAL RESTORE = READY',
            'PRODUCT ID = ' . $productId,
            'RU / EN / META = RESTORED FROM BACKUP',
            'BACKUP = PRESERVED',
        ];
    }

    /**
     * @param array<string,mixed> $legacy
     * @param array{status:string,signals:list<string>,ruFacts:list<string>,enFacts:list<string>} $official
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $generic
     * @return array<string,mixed>
     */
    private function enrichLegacy(array $legacy, array $official, array $generic): array
    {
        $ruShort = $this->plainEditorialText((string) ($legacy['ruShort'] ?? ''));
        $ruLong = $this->plainEditorialText((string) ($legacy['ruLong'] ?? ''));
        if ($official['ruFacts'] !== []) {
            if ($this->sameEditorialText($ruShort, $ruLong)) {
                $tail = $this->detailTail($ruLong);
                if ($tail !== '') {
                    $legacy['ruLong'] = $tail;
                }
            }
            $legacy['ruLong'] = $this->appendFacts(
                (string) ($legacy['ruLong'] ?? ''),
                $official['ruFacts']
            );
        }

        $enShort = $this->plainEditorialText((string) ($legacy['enShort'] ?? ''));
        $enLong = $this->plainEditorialText((string) ($legacy['enLong'] ?? ''));
        if ($official['enFacts'] !== []) {
            if ($enShort === '' && $enLong === '') {
                $legacy['enShort'] = $generic['enShort'];
                $legacy['enLong'] = '';
            } elseif ($this->sameEditorialText($enShort, $enLong)) {
                $tail = $this->detailTail($enLong);
                if ($tail !== '') {
                    $legacy['enLong'] = $tail;
                }
            }
            $legacy['enLong'] = $this->appendFacts(
                (string) ($legacy['enLong'] ?? ''),
                $official['enFacts']
            );
        }

        return $legacy;
    }

    /**
     * Keep an established human-written meta description when it has enough
     * substance to be safer than a generated summary.
     *
     * @param array<string,mixed> $legacy
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $generated
     * @return array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     */
    private function preserveQualityMeta(array $legacy, array $generated, string $productType): array
    {
        foreach (['ruMeta', 'enMeta'] as $field) {
            $value = trim((string) ($legacy[$field] ?? ''));
            $language = $field === 'ruMeta' ? 'ru' : 'en';
            $shortField = $language === 'ru' ? 'ruShort' : 'enShort';
            $longField = $language === 'ru' ? 'ruLong' : 'enLong';
            $typeSource = trim((string) ($legacy[$shortField] ?? '')) . ' '
                . trim((string) ($legacy[$longField] ?? ''));
            if ($this->legacyTypeConflict($typeSource, $productType, $language)) {
                continue;
            }
            if (mb_strlen($this->plainEditorialText($value), 'UTF-8') >= 80) {
                $generated[$field] = $value;
            }
        }
        return $generated;
    }

    /**
     * Preserve already structured human-written RU and EN editorial pages
     * instead of rebuilding them into a weaker generic v28 template.
     *
     * @param array<string,mixed> $legacy
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $generated
     * @return array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string}
     */
    private function preserveRichLegacyRu(array $legacy, array $generated, string $productType): array
    {
        foreach ([['ruShort', 'ruLong'], ['enShort', 'enLong']] as [$shortField, $longField]) {
            $long = trim((string) ($legacy[$longField] ?? ''));
            if (! $this->isRichEditorialContent($long)) {
                continue;
            }
            $short = trim((string) ($legacy[$shortField] ?? ''));
            $language = $shortField === 'ruShort' ? 'ru' : 'en';
            if ($this->legacyTypeConflict($short . ' ' . $long, $productType, $language)) {
                continue;
            }
            if ($short !== '') {
                $generated[$shortField] = $short;
            }
            $generated[$longField] = $long;
        }
        return $generated;
    }

    private function legacyTypeConflict(string $content, string $productType, string $language): bool
    {
        $plain = $this->plainEditorialText($content);
        if ($plain === '') {
            return false;
        }
        $head = mb_substr($plain, 0, 260, 'UTF-8');
        if ($language === 'ru') {
            $hasTheme = preg_match('/\b(?:тема|шаблон)\b/ui', $head) === 1;
            $hasPlugin = preg_match('/\b(?:плагин|расширение)\b/ui', $head) === 1;
        } else {
            $hasTheme = preg_match('/\btheme\b/ui', $head) === 1;
            $hasPlugin = preg_match('/\b(?:plugin|add-on|addon|extension)\b/ui', $head) === 1;
        }
        if ($productType === CatalogProductType::PLUGIN) {
            return $hasTheme && ! $hasPlugin;
        }
        if ($productType === CatalogProductType::THEME) {
            return $hasPlugin && ! $hasTheme;
        }
        return false;
    }

    private function isRichEditorialContent(string $content): bool
    {
        if (mb_strlen($this->plainEditorialText($content), 'UTF-8') < 350) {
            return false;
        }

        if (preg_match('/<h2\b[^>]*>/i', $content) !== 1) {
            return false;
        }

        $paragraphs = preg_match_all('/<p\b[^>]*>/i', $content);
        $sections = preg_match_all('/<(?:h3|ul|ol)\b[^>]*>/i', $content);

        return (is_int($paragraphs) && $paragraphs >= 3)
            || (is_int($sections) && $sections >= 1);
    }

    /** @param array<string,mixed> $legacy */
    private function requiresEnglishTranslation(array $legacy): bool
    {
        $hasRuEditorial = trim((string) ($legacy['ruShort'] ?? '')) !== ''
            || trim((string) ($legacy['ruLong'] ?? '')) !== '';
        if (! $hasRuEditorial) {
            return false;
        }

        foreach (['enShort', 'enLong', 'enMeta'] as $field) {
            if (trim((string) ($legacy[$field] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function translationIssue(array $content): string
    {
        try {
            (new TranslationMapBuilder())->build(
                $content['ruShort'],
                $content['ruLong'],
                $content['ruMeta'],
                $content['enShort'],
                $content['enLong'],
                $content['enMeta']
            );
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return '';
    }

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function assertTranslationCompatible(int $productId, array $content): void
    {
        $issue = $this->translationIssue($content);
        if ($issue === '') {
            return;
        }

        throw new RuntimeException(
            'Editorial migration stopped before write: incompatible RU/EN structure for product '
            . $productId . '. ' . $issue
        );
    }

    /** @param list<string> $facts */
    private function appendFacts(string $content, array $facts): string
    {
        $content = trim($content);
        $existing = mb_strtolower($this->plainEditorialText($content), 'UTF-8');
        $newFacts = [];
        foreach ($facts as $fact) {
            $fact = trim($fact);
            if ($fact === '') {
                continue;
            }
            if (mb_strpos($existing, mb_strtolower($fact, 'UTF-8')) !== false) {
                continue;
            }
            $newFacts[] = $fact;
        }

        if ($newFacts === []) {
            return $content;
        }

        $line = implode(', ', array_values(array_unique($newFacts))) . '.';
        return $content === '' ? $line : $content . ' ' . $line;
    }

    private function sameEditorialText(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }
        return $this->normalize($left) === $this->normalize($right);
    }

    private function plainEditorialText(string $content): string
    {
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $content));
    }

    private function detailTail(string $content): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($content), 2);
        if (! is_array($parts) || count($parts) < 2) {
            return '';
        }
        return trim((string) $parts[1]);
    }

    /**
     * @param list<string> $signals
     * @return list<string>
     */
    private function semanticSignals(array $signals): array
    {
        $technical = [
            'elementor',
            'elementor pro',
            'woocommerce',
            'wpml',
            'rtl',
            'loco translate',
            'translation ready',
            'learnpress',
            'learndash',
            'lifterlms',
            'sensei',
            'tutor',
            'tutor lms',
            'bbpress',
            'buddypress',
            'gutenberg',
            'contact form 7',
        ];
        $result = [];
        foreach ($signals as $signal) {
            $normalized = mb_strtolower(trim($signal), 'UTF-8');
            if ($normalized === '' || in_array($normalized, $technical, true)) {
                continue;
            }
            $result[] = trim($signal);
        }
        return array_values(array_unique($result));
    }

    /** @return array{status:string,signals:list<string>,ruFacts:list<string>,enFacts:list<string>} */
    private function officialFacts(int $productId): array
    {
        $empty = [
            'status' => 'NOT REQUESTED',
            'signals' => [],
            'ruFacts' => [],
            'enFacts' => [],
        ];
        if ($this->envato === null || ! $this->officialRequested($productId)) {
            return $empty;
        }

        $salesPage = $this->meta($productId, 'sales_page');
        $token = $this->envatoToken();
        if ($salesPage === '' || $token === '') {
            $empty['status'] = 'NOT AVAILABLE';
            return $empty;
        }

        try {
            $facts = $this->officialExtractor->extract(
                $this->envato->fetch($salesPage, $token)->source
            );
            $facts['status'] = $facts['ruFacts'] === []
                ? 'READY / NO EXTRA FACTS'
                : 'READY';
            return $facts;
        } catch (Throwable) {
            $empty['status'] = 'FAILED / LEGACY FALLBACK';
            return $empty;
        }
    }

    private function officialRequested(int $productId): bool
    {
        if (
            (int) ($_REQUEST['preview_id'] ?? 0) === $productId
            || (int) ($_POST['editorial_apply_id'] ?? 0) === $productId
        ) {
            return true;
        }

        $selected = $_POST['editorial_selected'] ?? [];
        if (! is_array($selected)) {
            return false;
        }
        foreach ($selected as $value) {
            if ((int) $value === $productId) {
                return true;
            }
        }
        return false;
    }

    private function envatoToken(): string
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

    /**
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     */
    private function writeContent(int $productId, array $content): void
    {
        $updated = ($this->call)(
            'wp_update_post',
            [
                'ID' => $productId,
                'post_excerpt' => (string) ($this->call)('wp_kses_post', $content['ruShort']),
                'post_content' => (string) ($this->call)('wp_kses_post', $content['ruLong']),
            ],
            true
        );
        if (is_int($updated) && $updated <= 0) {
            throw new RuntimeException('Product content update failed.');
        }
        if (is_object($updated) && method_exists($updated, 'get_error_message')) {
            throw new RuntimeException(
                'Product content update failed: ' . (string) $updated->get_error_message()
            );
        }

        $settings = ($this->call)('get_post_meta', $productId, 'surerank_settings_general', true);
        $settings = is_array($settings) ? $settings : [];
        $settings['page_description'] = (string) ($this->call)(
            'sanitize_textarea_field',
            $content['ruMeta']
        );
        ($this->call)('update_post_meta', $productId, 'surerank_settings_general', $settings);

        foreach ([
            '_wp_shop_en_short_description' => $content['enShort'],
            '_wp_shop_en_long_description' => $content['enLong'],
        ] as $key => $value) {
            ($this->call)(
                'update_post_meta',
                $productId,
                $key,
                (string) ($this->call)('wp_kses_post', $value)
            );
        }

        ($this->call)(
            'update_post_meta',
            $productId,
            '_wp_shop_en_meta_description',
            (string) ($this->call)('sanitize_textarea_field', $content['enMeta'])
        );
    }

    private function baseTitle(int $productId, string $title): string
    {
        $version = trim($this->meta($productId, 'attr_version_value'));
        if ($version === '' || $version === '—') {
            return $title;
        }

        $suffix = ' ' . $version;
        return str_ends_with($title, $suffix)
            ? trim(substr($title, 0, -strlen($suffix)))
            : $title;
    }

    private function productType(
        int $productId,
        string $baseTitle,
        string $content = ''
    ): string {
        $manualOverride = trim($this->meta($productId, self::TYPE_OVERRIDE_META));
        if ($this->validTechnicalType($manualOverride)) {
            return $manualOverride;
        }

        $stored = trim($this->meta($productId, '_wp_shop_product_type'));
        if (in_array($stored, [
            CatalogProductType::THEME,
            CatalogProductType::PLUGIN,
            CatalogProductType::TEMPLATE_KIT,
        ], true)) {
            return $stored;
        }

        $category = mb_strtolower(
            trim($this->meta($productId, 'attr_category_value')),
            'UTF-8'
        );
        if (in_array($category, ['шаблоны', 'templates'], true)) {
            return CatalogProductType::TEMPLATE_KIT;
        }
        if (in_array($category, ['плагины', 'plugins'], true)) {
            return CatalogProductType::PLUGIN;
        }
        if (in_array($category, ['темы', 'themes'], true)) {
            return CatalogProductType::THEME;
        }

        $terms = ($this->call)(
            'wp_get_post_terms',
            $productId,
            'product_cat',
            ['fields' => 'names']
        );
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $normalized = mb_strtolower(trim((string) $term), 'UTF-8');
                if (in_array($normalized, ['шаблоны', 'templates'], true)) {
                    return CatalogProductType::TEMPLATE_KIT;
                }
                if (in_array($normalized, ['плагины', 'plugins'], true)) {
                    return CatalogProductType::PLUGIN;
                }
                if (in_array($normalized, ['темы', 'themes'], true)) {
                    return CatalogProductType::THEME;
                }
            }
        }

        return CatalogProductType::infer(
            $baseTitle,
            $this->meta($productId, 'sales_page'),
            $content
        );
    }

    /** @return list<string> */
    private function signals(int $productId, string $baseTitle): array
    {
        $signals = [];
        foreach (['product_tag', 'pa_tags'] as $taxonomy) {
            $terms = ($this->call)(
                'wp_get_post_terms',
                $productId,
                $taxonomy,
                ['fields' => 'names']
            );
            if (! is_array($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                if (is_scalar($term) && trim((string) $term) !== '') {
                    $signals[] = trim((string) $term);
                }
            }
        }

        $titleParts = preg_split('/[^a-z0-9-]+/i', $baseTitle) ?: [];
        foreach ($titleParts as $part) {
            if (strlen(trim($part)) >= 4) {
                $signals[] = trim($part);
            }
        }
        if (str_contains(strtolower($baseTitle), 'real estate')) {
            $signals[] = 'real estate';
        }

        return array_values(array_unique($signals));
    }

    private function sureRankMeta(int $productId): string
    {
        $settings = ($this->call)(
            'get_post_meta',
            $productId,
            'surerank_settings_general',
            true
        );
        return is_array($settings)
            ? trim((string) ($settings['page_description'] ?? ''))
            : '';
    }

    private function meta(int $productId, string $key): string
    {
        $value = ($this->call)('get_post_meta', $productId, $key, true);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function singleStatus(string $current, string $generated): string
    {
        if (trim($current) === '') {
            return 'MISSING';
        }
        return $this->normalize($current) === $this->normalize($generated)
            ? 'CURRENT'
            : 'OLD';
    }

    /**
     * @param list<string> $current
     * @param list<string> $generated
     */
    private function status(array $current, array $generated): string
    {
        if ($this->allEmpty($current)) {
            return 'MISSING';
        }
        return $this->sameList($current, $generated) ? 'CURRENT' : 'OLD';
    }

    /** @param list<string> $values */
    private function allEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function sameList(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $index => $value) {
            if ($this->normalize($value) !== $this->normalize($right[$index] ?? '')) {
                return false;
            }
        }
        return true;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function now(): string
    {
        $value = ($this->call)('current_time', 'mysql', true);
        return is_scalar($value) ? trim((string) $value) : gmdate('Y-m-d H:i:s');
    }
}
