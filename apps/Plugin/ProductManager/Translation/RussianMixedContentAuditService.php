<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use Closure;

final class RussianMixedContentAuditService
{
    /**
     * Exact names that are valid inside Russian editorial copy.
     *
     * @var list<string>
     */
    private const KNOWN_BRANDS = [
        'Advanced Custom Fields',
        'ACF',
        'Blocksy Companion',
        'Cloudflare',
        'Core Web Vitals',
        'Crocoblock',
        'Easy Digital Downloads',
        'Elementor',
        'Elementor Pro',
        'Fluent Forms',
        'Fluent Support',
        'FluentCRM',
        'GenerateBlocks',
        'GeneratePress',
        'GitHub',
        'Google Analytics',
        'Google Calendar',
        'Google Maps',
        'Google News',
        'Google Search Console',
        'Google Tag Manager',
        'Google Videos',
        'Gravity Forms',
        'JetAppointment',
        'JetBooking',
        'JetEngine',
        'JetFormBuilder',
        'JetSmartFilters',
        'JetWooBuilder',
        'Justified Image Grid Premium',
        'LearnDash',
        'LifterLMS',
        'Loco Translate',
        'Mailchimp',
        'MailPoet',
        'Meta Box',
        'OpenAI',
        'Outlook Calendar',
        'PayPal',
        'Polylang',
        'Rank Math',
        'Stripe',
        'SureRank',
        'TranslatePress',
        'Tutor LMS',
        'WooCommerce',
        'WooPayments',
        'WordPress',
        'WP All Export',
        'WP All Import',
        'WP Rocket',
        'WPML',
        'WPMU DEV',
        'Yoast SEO',
    ];

    /**
     * Technical identifiers and technology names that are acceptable
     * untranslated inside otherwise Russian editorial copy.
     *
     * Ordinary English UI/feature wording is intentionally NOT listed here.
     *
     * @var list<string>
     */
    private const ALLOWED_TECHNICAL_TOKENS = [
        'ajax',
        'api',
        'avif',
        'cdn',
        'cron',
        'css',
        'csv',
        'dns',
        'ean',
        'ftp',
        'gtin',
        'html',
        'http',
        'https',
        'imap',
        'javascript',
        'jpeg',
        'jpg',
        'jquery',
        'json',
        'json-ld',
        'jwt',
        'mariadb',
        'memcached',
        'mysql',
        'oauth',
        'php',
        'png',
        'pop3',
        'redis',
        'rest',
        'rss',
        'schema.org',
        'seo',
        'sftp',
        'sku',
        'smtp',
        'sql',
        'ssh',
        'ssl',
        'svg',
        'tls',
        'upc',
        'uri',
        'url',
        'webp',
        'xml',
        'xls',
        'xlsx',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_TECHNICAL_TERMS = [
        'open graph',
        'rest api',
    ];

    /**
     * Generic title words must not make an ordinary English word look like
     * a one-word product name.
     *
     * @var list<string>
     */
    private const GENERIC_TITLE_WORDS = [
        'addon',
        'extension',
        'plugin',
        'premium',
        'pro',
        'template',
        'theme',
    ];

    /**
     * @param Closure(string, mixed...): mixed $call
     */
    public function __construct(
        private readonly Closure $call
    ) {
    }

    public function candidateCount(): int
    {
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        return is_array($ids) ? count($ids) : 0;
    }

    /**
     * @return list<RussianMixedContentAuditRow>
     */
    public function scan(int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(50, $limit));
        $ids = ($this->call)(
            'get_posts',
            [
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private'],
                'fields' => 'ids',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
                'no_found_rows' => true,
            ]
        );

        if (! is_array($ids)) {
            return [];
        }

        $rows = [];

        foreach ($ids as $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0) {
                continue;
            }

            $title = trim((string) ($this->call)(
                'get_post_field',
                'post_title',
                $productId
            ));
            $fields = [
                'RU_SHORT' => (string) ($this->call)(
                    'get_post_field',
                    'post_excerpt',
                    $productId
                ),
                'RU_LONG' => (string) ($this->call)(
                    'get_post_field',
                    'post_content',
                    $productId
                ),
                'RU_META' => $this->sureRankMeta($productId),
            ];

            $findings = [];

            foreach ($fields as $field => $value) {
                foreach (
                    $this->findings(
                        $field,
                        $value,
                        $title
                    ) as $finding
                ) {
                    $findings[] = $finding;
                }
            }

            $status = 'CLEAN';

            foreach ($findings as $finding) {
                if ($finding->classification === 'TRANSLATE') {
                    $status = 'REVIEW';
                    break;
                }

                $status = 'INFO';
            }

            $rows[] = new RussianMixedContentAuditRow(
                $productId,
                $title !== '' ? $title : 'Product #' . $productId,
                $status,
                $findings
            );
        }

        return $rows;
    }

    /**
     * @return list<RussianMixedContentAuditFinding>
     */
    private function findings(
        string $field,
        string $value,
        string $productTitle
    ): array {
        $plain = $this->plainText($value);

        if ($plain === '') {
            return [];
        }

        $segments = preg_split(
            '/(?<=[.!?])\s+|\R+/u',
            $plain
        );

        if (! is_array($segments)) {
            $segments = [$plain];
        }

        $findings = [];
        $seen = [];
        $pattern = '/\b[A-Za-z][A-Za-z0-9+#.&\'’\/-]*'
            . '(?:\s+[A-Za-z][A-Za-z0-9+#.&\'’\/-]*){0,11}\b/u';

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $matches = [];
            $count = preg_match_all(
                $pattern,
                $segment,
                $matches
            );

            if (! is_int($count) || $count <= 0) {
                continue;
            }

            foreach ($matches[0] as $rawFragment) {
                $fragment = $this->cleanFragment((string) $rawFragment);

                if ($fragment === '') {
                    continue;
                }

                $classification = $this->classify(
                    $fragment,
                    $productTitle
                );

                if ($classification === '') {
                    continue;
                }

                $key = $field
                    . "\0"
                    . $classification
                    . "\0"
                    . $this->normalize($fragment);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $findings[] = new RussianMixedContentAuditFinding(
                    $field,
                    $classification,
                    $fragment,
                    $this->context($segment, $fragment)
                );
            }
        }

        return $findings;
    }

    private function plainText(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        $value = preg_replace(
            '/<(script|style|pre|code)\b[^>]*>.*?<\/\1>/is',
            ' ',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/\[[^\]\r\n]{1,500}\]/u',
            ' ',
            $value
        ) ?? $value;
        $value = preg_replace(
            '~(?:https?://|www\.)\S+~iu',
            ' ',
            $value
        ) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function cleanFragment(string $fragment): string
    {
        $fragment = preg_replace(
            '/\s+/u',
            ' ',
            trim($fragment)
        ) ?? trim($fragment);
        $fragment = trim(
            $fragment,
            " \t\n\r\0\x0B.,:;!?()[]{}<>\"“”'’–—-"
        );

        return $fragment;
    }

    private function classify(
        string $fragment,
        string $productTitle
    ): string {
        if ($this->wordCount($fragment) <= 0) {
            return '';
        }

        if (
            $this->isProductName($fragment, $productTitle)
            || $this->isKnownBrandName($fragment)
        ) {
            return 'BRAND_NAME';
        }

        if ($this->isAllowedTechnicalFragment($fragment)) {
            return 'TECH_ALLOWED';
        }

        return 'TRANSLATE';
    }

    private function isProductName(
        string $fragment,
        string $productTitle
    ): bool {
        $fragment = trim($fragment);
        $productTitle = trim($productTitle);

        if ($fragment === '' || $productTitle === '') {
            return false;
        }

        $normalizedFragment = $this->normalize($fragment);
        $normalizedTitle = $this->normalize($productTitle);

        if ($normalizedFragment === $normalizedTitle) {
            return true;
        }

        $fragmentWords = $this->titleWords($fragment);
        $titleWords = $this->titleWords($productTitle);

        if (count($fragmentWords) < 1 || count($titleWords) < 1) {
            return false;
        }

        if (count($fragmentWords) === 1) {
            $word = $fragmentWords[0];
            $firstTitleWord = $titleWords[0];

            return $word === $firstTitleWord
                && strlen($word) >= 3
                && ! in_array(
                    $word,
                    self::GENERIC_TITLE_WORDS,
                    true
                );
        }

        $fragmentCompact = $this->compactLatin($fragment);
        $titleCompact = $this->compactLatin($productTitle);

        if (
            $fragmentCompact !== ''
            && $titleCompact !== ''
            && str_contains($titleCompact, $fragmentCompact)
        ) {
            return true;
        }

        if (
            $fragmentCompact !== ''
            && $titleCompact !== ''
            && count($fragmentWords) <= count($titleWords) + 2
            && str_contains($fragmentCompact, $titleCompact)
        ) {
            return true;
        }

        $matched = count(array_intersect(
            $fragmentWords,
            $titleWords
        ));
        $coverage = $matched / count($fragmentWords);

        if (
            count($fragmentWords) <= count($titleWords) + 2
            && $coverage >= 0.75
        ) {
            return true;
        }

        if (
            $fragmentCompact !== ''
            && $titleCompact !== ''
            && count($fragmentWords) <= count($titleWords) + 2
        ) {
            $similarity = 0.0;
            similar_text(
                $fragmentCompact,
                $titleCompact,
                $similarity
            );

            if ($similarity >= 75.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function titleWords(string $value): array
    {
        $value = preg_replace(
            '/(?<=[a-z])(?=[A-Z])/u',
            ' ',
            $value
        ) ?? $value;
        $matches = [];
        $count = preg_match_all(
            '/[A-Za-z0-9]+/u',
            $value,
            $matches
        );

        if (! is_int($count) || $count <= 0) {
            return [];
        }

        $words = array_map(
            static fn(string $word): string => strtolower($word),
            $matches[0]
        );

        return array_values(array_unique($words));
    }

    private function compactLatin(string $value): string
    {
        $value = strtolower($value);

        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            $value
        ) ?? '';
    }

    private function isKnownBrandName(string $fragment): bool
    {
        $normalized = $this->normalize($fragment);

        foreach (self::KNOWN_BRANDS as $brand) {
            $brandNormalized = $this->normalize($brand);

            if ($normalized === $brandNormalized) {
                return true;
            }

            if (
                str_starts_with(
                    $normalized,
                    $brandNormalized . ' '
                )
            ) {
                $tail = trim(substr(
                    $normalized,
                    strlen($brandNormalized)
                ));

                if (
                    in_array(
                        $tail,
                        ['pro', 'premium', 'plugin', 'addon', 'add-on', 'extension'],
                        true
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAllowedTechnicalFragment(
        string $fragment
    ): bool {
        $normalized = $this->normalize($fragment);

        if ($this->isAllowedTechnicalTail($normalized)) {
            return true;
        }

        foreach (self::KNOWN_BRANDS as $brand) {
            $brandNormalized = $this->normalize($brand);

            if (
                ! str_starts_with(
                    $normalized,
                    $brandNormalized . ' '
                )
            ) {
                continue;
            }

            $tail = trim(substr(
                $normalized,
                strlen($brandNormalized)
            ));

            if ($this->isAllowedTechnicalTail($tail)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedTechnicalTail(string $value): bool
    {
        $value = $this->normalize($value);

        if ($value === '') {
            return false;
        }

        if (
            in_array(
                $value,
                self::ALLOWED_TECHNICAL_TERMS,
                true
            )
        ) {
            return true;
        }

        $tokens = preg_split(
            '/[\\s,\/]+/u',
            $value
        );

        if (! is_array($tokens)) {
            return false;
        }

        foreach ($tokens as $token) {
            $token = trim($token);

            if (
                $token === ''
                || ! in_array(
                    $token,
                    self::ALLOWED_TECHNICAL_TOKENS,
                    true
                )
            ) {
                return false;
            }
        }

        return true;
    }

    private function context(
        string $segment,
        string $fragment
    ): string {
        $segment = trim(
            preg_replace('/\s+/u', ' ', $segment) ?? $segment
        );

        if (mb_strlen($segment, 'UTF-8') <= 260) {
            return $segment;
        }

        $position = mb_stripos(
            $segment,
            $fragment,
            0,
            'UTF-8'
        );

        if ($position === false) {
            return mb_substr(
                $segment,
                0,
                257,
                'UTF-8'
            ) . '…';
        }

        $start = max(0, $position - 80);
        $slice = mb_substr(
            $segment,
            $start,
            240,
            'UTF-8'
        );

        return ($start > 0 ? '…' : '')
            . $slice
            . (
                $start + mb_strlen($slice, 'UTF-8')
                    < mb_strlen($segment, 'UTF-8')
                ? '…'
                : ''
            );
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

    private function wordCount(string $value): int
    {
        $matches = [];
        $count = preg_match_all(
            '/[A-Za-z][A-Za-z0-9+#.&\'’\/-]*/u',
            $value,
            $matches
        );

        return is_int($count) ? $count : 0;
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['’', '–', '—'], ["'", '-', '-'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
