<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

use Closure;
use Throwable;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class EnvatoItemSearchResolver
{
    /** @var array<string, EnvatoItemSearchResult> */
    private array $cache = [];

    /**
     * @param Closure(string, array<string,string>): array<string,mixed> $getJson
     */
    public function __construct(
        private readonly Closure $getJson
    ) {
    }

    public function resolve(
        string $identityName,
        string $productType,
        string $token
    ): EnvatoItemSearchResult {
        $identityName = trim($identityName);
        $token = trim($token);

        if ($identityName === '' || $token === '') {
            return $this->failure('ENVATO AUTO-MATCH = NOT AVAILABLE');
        }

        $site = $productType === CatalogProductType::PLUGIN
            ? 'codecanyon.net'
            : ($productType === CatalogProductType::THEME
                ? 'themeforest.net'
                : '');

        if ($site === '') {
            return $this->failure(
                'ENVATO AUTO-MATCH = UNSUPPORTED PRODUCT TYPE'
            );
        }

        $cacheKey = strtolower($site . '|' . $identityName);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $url = 'https://api.envato.com/v1/discovery/search/search/item?'
            . http_build_query(
                [
                    'site' => $site,
                    'term' => $identityName,
                    'sort_by' => 'relevance',
                    'page_size' => 10,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );

        try {
            $payload = ($this->getJson)(
                $url,
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ]
            );
        } catch (Throwable $exception) {
            return $this->remember(
                $cacheKey,
                $this->failure(
                    'ENVATO AUTO-MATCH = API ERROR: '
                    . $exception->getMessage()
                )
            );
        }

        $matches = $this->matches($payload);

        if ($matches === []) {
            return $this->remember(
                $cacheKey,
                $this->failure('ENVATO AUTO-MATCH = NO CANDIDATES')
            );
        }

        $scored = [];

        foreach ($matches as $match) {
            $itemId = $this->integer($match['id'] ?? null);
            $title = $this->string(
                $match['name']
                    ?? $match['title']
                    ?? null
            );
            $candidateUrl = $this->string($match['url'] ?? null);

            if ($itemId <= 0 || $title === '') {
                continue;
            }

            if (
                $candidateUrl !== ''
                && strtolower(
                    (string) parse_url(
                        $candidateUrl,
                        PHP_URL_HOST
                    )
                ) !== $site
            ) {
                continue;
            }

            $score = $this->score(
                $identityName,
                $title
            );

            if ($score < 90) {
                continue;
            }

            $scored[] = [
                'id' => $itemId,
                'title' => $title,
                'url' => $candidateUrl,
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return $this->remember(
                $cacheKey,
                $this->failure(
                    'ENVATO AUTO-MATCH = NO HIGH-CONFIDENCE CANDIDATE'
                )
            );
        }

        usort(
            $scored,
            static fn (array $left, array $right): int =>
                $right['score'] <=> $left['score']
        );

        $best = $scored[0];
        $second = $scored[1] ?? null;

        if (
            is_array($second)
            && (int) $second['score'] === (int) $best['score']
        ) {
            return $this->remember(
                $cacheKey,
                $this->failure(
                    'ENVATO AUTO-MATCH = AMBIGUOUS TOP CANDIDATES'
                )
            );
        }

        $resolvedUrl = (string) $best['url'];

        if ($resolvedUrl === '') {
            $resolvedUrl = 'https://'
                . $site
                . '/item/product/'
                . (int) $best['id'];
        }

        return $this->remember(
            $cacheKey,
            new EnvatoItemSearchResult(
                true,
                (int) $best['id'],
                (string) $best['title'],
                $resolvedUrl,
                (int) $best['score'],
                'ENVATO AUTO-MATCH = READY'
            )
        );
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>>
     */
    private function matches(array $payload): array
    {
        $raw = $payload['matches']
            ?? (
                is_array($payload['data'] ?? null)
                    ? ($payload['data']['matches'] ?? null)
                    : null
            )
            ?? $payload['results']
            ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $matches = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                $matches[] = $item;
            }
        }

        return $matches;
    }

    private function score(
        string $identityName,
        string $candidateTitle
    ): int {
        $identityLead = $this->lead($identityName);
        $candidateLead = $this->lead($candidateTitle);

        if (
            $identityLead !== ''
            && $candidateLead !== ''
            && $identityLead === $candidateLead
        ) {
            return 100;
        }

        $identityNormalized = $this->normalize(
            $identityName
        );
        $candidateNormalized = $this->normalize(
            $candidateTitle
        );

        if (
            $identityNormalized !== ''
            && str_starts_with(
                $candidateNormalized,
                $identityNormalized
            )
        ) {
            return 97;
        }

        $tokens = $this->tokens($identityName);

        if (count($tokens) < 2) {
            return 0;
        }

        foreach ($tokens as $token) {
            if (
                ! str_contains(
                    ' ' . $candidateNormalized . ' ',
                    ' ' . $token . ' '
                )
            ) {
                return 0;
            }
        }

        return count($tokens) >= 3 ? 94 : 90;
    }

    private function lead(string $value): string
    {
        $parts = preg_split(
            '/\s*(?:\||–|—|\s+-\s+|:)\s*/u',
            trim($value)
        );
        $lead = is_array($parts)
            ? (string) ($parts[0] ?? '')
            : $value;

        return $this->normalize($lead);
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = strtolower($value);
        $value = preg_replace(
            '/[^a-z0-9]+/i',
            ' ',
            $value
        );

        return is_string($value)
            ? trim(
                preg_replace('/\s+/', ' ', $value)
                ?? ''
            )
            : '';
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $tokens = preg_split(
            '/\s+/',
            $this->normalize($value)
        ) ?: [];
        $stop = [
            'wordpress',
            'woocommerce',
            'theme',
            'plugin',
            'template',
            'elementor',
            'responsive',
            'multipurpose',
            'ecommerce',
            'for',
            'the',
            'and',
            'with',
            'pro',
        ];
        $result = [];

        foreach ($tokens as $token) {
            if (
                strlen($token) >= 4
                && ! in_array($token, $stop, true)
            ) {
                $result[] = $token;
            }
        }

        return array_values(
            array_unique(
                array_slice($result, 0, 5)
            )
        );
    }

    private function string(mixed $value): string
    {
        return is_scalar($value)
            ? trim((string) $value)
            : '';
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value)
            ? (int) $value
            : 0;
    }

    private function failure(
        string $message
    ): EnvatoItemSearchResult {
        return new EnvatoItemSearchResult(
            false,
            0,
            '',
            '',
            0,
            $message
        );
    }

    private function remember(
        string $key,
        EnvatoItemSearchResult $result
    ): EnvatoItemSearchResult {
        $this->cache[$key] = $result;

        return $result;
    }
}
