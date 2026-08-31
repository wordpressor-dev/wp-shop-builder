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
            $generated = $this->preserveQualityMeta($legacy, $generated);
            $generated = $this->preserveRichLegacyRu($legacy, $generated);
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
            $generated = $this->preserveQualityMeta($legacyWithoutEnglish, $generated);
            $generated = $this->preserveRichLegacyRu($legacyWithoutEnglish, $generated);
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
     * @param array{ruShort:string,ruLong:string,ruMeta:string,enShort:string,enLong:string,enMeta:string} $content
     * @return list<string>
     */
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
    private function preserveQualityMeta(array $legacy, array $generated): array
    {
        foreach (['ruMeta', 'enMeta'] as $field) {
            $value = trim((string) ($legacy[$field] ?? ''));
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
    private function preserveRichLegacyRu(array $legacy, array $generated): array
    {
        foreach (
            [
                ['ruShort', 'ruLong'],
                ['enShort', 'enLong'],
            ] as [$shortField, $longField]
        ) {
            $long = trim((string) ($legacy[$longField] ?? ''));
            if (! $this->isRichEditorialContent($long)) {
                continue;
            }

            $short = trim((string) ($legacy[$shortField] ?? ''));
            if ($short !== '') {
                $generated[$shortField] = $short;
            }
            $generated[$longField] = $long;
        }

        return $generated;
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
