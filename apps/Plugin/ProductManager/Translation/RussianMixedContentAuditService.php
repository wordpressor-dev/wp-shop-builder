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
     * Common technical noun phrases that are acceptable untranslated.
     *
     * @var list<string>
     */
    private const TECHNICAL_TERMS = [
        'backup profiles',
        'browser cache',
        'cache preload',
        'child theme',
        'checkout fields',
        'critical css',
        'custom fields',
        'custom post types',
        'custom taxonomy',
        'dynamic content',
        'dynamic tags',
        'email templates',
        'faceted search',
        'header builder',
        'footer builder',
        'lazy load',
        'license key',
        'live search',
        'magic links',
        'mega menu',
        'object cache',
        'page cache',
        'popup builder',
        'product bundles',
        'quick view',
        'rest api',
        'role editor',
        'social login',
        'starter sites',
        'sticky header',
        'theme builder',
        'variation swatches',
        'white label',
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
                if ($finding->classification === 'MIXED_PROSE') {
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
            " \t\n\r\0\x0B.,:;!?()[]{}<>\"“”'’"
        );

        return $fragment;
    }

    private function classify(
        string $fragment,
        string $productTitle
    ): string {
        $wordCount = $this->wordCount($fragment);

        if ($wordCount <= 0) {
            return '';
        }

        if (
            $this->isProductName($fragment, $productTitle)
            || $this->isKnownBrandName($fragment)
        ) {
            return 'BRAND_NAME';
        }

        if ($wordCount === 1) {
            return '';
        }

        if ($this->isTechnicalTerm($fragment)) {
            return 'TERM_ONLY';
        }

        if ($this->containsKnownBrand($fragment)) {
            return 'TERM_ONLY';
        }

        if (
            $wordCount >= 5
            || ($wordCount >= 3 && $this->hasConnector($fragment))
            || $wordCount >= 4
        ) {
            return 'MIXED_PROSE';
        }

        return 'TERM_ONLY';
    }

    private function isProductName(
        string $fragment,
        string $productTitle
    ): bool {
        $fragment = $this->normalize($fragment);
        $title = $this->normalize($productTitle);

        if ($fragment === '' || $title === '') {
            return false;
        }

        if ($fragment === $title) {
            return true;
        }

        if (
            $this->wordCount($fragment) >= 2
            && str_contains($title, $fragment)
        ) {
            return true;
        }

        return false;
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

    private function containsKnownBrand(string $fragment): bool
    {
        $normalized = ' ' . $this->normalize($fragment) . ' ';

        foreach (self::KNOWN_BRANDS as $brand) {
            $needle = ' ' . $this->normalize($brand) . ' ';

            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isTechnicalTerm(string $fragment): bool
    {
        $normalized = $this->normalize($fragment);

        foreach (self::TECHNICAL_TERMS as $term) {
            if ($normalized === $term) {
                return true;
            }
        }

        return false;
    }

    private function hasConnector(string $fragment): bool
    {
        $normalized = preg_replace(
            '/[^a-z]+/',
            ' ',
            $this->normalize($fragment)
        ) ?? '';
        $tokens = array_values(array_filter(
            explode(' ', trim($normalized)),
            static fn(string $token): bool => $token !== ''
        ));
        $connectors = [
            'a', 'an', 'and', 'are', 'be', 'been', 'being', 'by',
            'can', 'for', 'from', 'helps', 'into', 'is', 'of', 'or',
            'our', 'that', 'the', 'to', 'using', 'was', 'we', 'were',
            'which', 'who', 'will', 'with', 'without', 'you', 'your',
        ];

        foreach ($tokens as $token) {
            if (in_array($token, $connectors, true)) {
                return true;
            }
        }

        return false;
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
