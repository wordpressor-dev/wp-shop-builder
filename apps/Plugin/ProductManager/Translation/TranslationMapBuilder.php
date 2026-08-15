<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Translation;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

final class TranslationMapBuilder
{
    /**
     * @return array<string, string>
     */
    public function build(
        string $ruShort,
        string $ruLong,
        string $ruMeta,
        string $enShort,
        string $enLong,
        string $enMeta
    ): array {
        if (
            trim($ruShort) === ''
            || trim($ruLong) === ''
            || trim($ruMeta) === ''
        ) {
            throw new InvalidArgumentException(
                'RU Short/Long/SureRank Meta must be complete before EN translation.'
            );
        }

        if (
            trim($enShort) === ''
            || trim($enLong) === ''
            || trim($enMeta) === ''
        ) {
            throw new InvalidArgumentException(
                'EN Short/Long/Meta is incomplete.'
            );
        }

        $shortMap = $this->pairMap(
            $ruShort,
            $enShort,
            'SHORT'
        );
        $longMap = $this->pairMap(
            $ruLong,
            $enLong,
            'LONG'
        );
        $map = [];

        foreach ([$shortMap, $longMap] as $part) {
            foreach ($part as $source => $target) {
                if (
                    isset($map[$source])
                    && $this->normalize($map[$source])
                        !== $this->normalize($target)
                ) {
                    throw new InvalidArgumentException(
                        'Conflicting EN translation for RU segment: "'
                        . $source . '".'
                    );
                }

                $map[$source] = $target;
            }
        }

        $normalizedRuMeta = $this->normalize($ruMeta);
        $normalizedEnMeta = $this->normalize($enMeta);

        if ($normalizedRuMeta === '' || $normalizedEnMeta === '') {
            throw new InvalidArgumentException(
                'RU/EN Meta Description is empty.'
            );
        }

        if (
            isset($map[$normalizedRuMeta])
            && $this->normalize($map[$normalizedRuMeta])
                !== $normalizedEnMeta
        ) {
            throw new InvalidArgumentException(
                'Conflicting EN translation for RU segment: "'
                . $normalizedRuMeta . '".'
            );
        }

        $map[$normalizedRuMeta] = $normalizedEnMeta;

        return $map;
    }

    public function normalize(string $text): string
    {
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $normalized = preg_replace(
            '/\s+/u',
            ' ',
            trim($text)
        );

        return is_string($normalized)
            ? $normalized
            : '';
    }

    /**
     * @return array<string, string>
     */
    private function pairMap(
        string $ruHtml,
        string $enHtml,
        string $label
    ): array {
        $ru = $this->htmlSegments($ruHtml);
        $en = $this->htmlSegments($enHtml);

        if (count($ru) !== count($en)) {
            throw new InvalidArgumentException(
                $label
                . ': RU segments=' . count($ru)
                . ', EN segments=' . count($en)
                . '. Keep the same HTML structure in RU and EN.'
            );
        }

        $map = [];

        foreach ($ru as $index => $source) {
            $target = $en[$index] ?? '';

            if ($source === '' || $target === '') {
                continue;
            }

            $sourceHasText = preg_match(
                '/[\p{L}\p{N}]/u',
                $source
            ) === 1;
            $targetHasText = preg_match(
                '/[\p{L}\p{N}]/u',
                $target
            ) === 1;

            if (! $sourceHasText && ! $targetHasText) {
                continue;
            }

            if (
                isset($map[$source])
                && $this->normalize($map[$source])
                    !== $this->normalize($target)
            ) {
                throw new InvalidArgumentException(
                    $label
                    . ': the same RU segment has different EN translations: "'
                    . $source . '".'
                );
            }

            $map[$source] = $target;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function htmlSegments(string $html): array
    {
        $segments = [];

        if (
            class_exists(DOMDocument::class)
            && class_exists(DOMXPath::class)
        ) {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument('1.0', 'UTF-8');
            $wrapped = '<div id="wp-shop-v14-root">'
                . $html . '</div>';
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8">' . $wrapped,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if ($loaded) {
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query(
                    '//*[@id="wp-shop-v14-root"]//text()'
                );

                if ($nodes !== false) {
                    foreach ($nodes as $node) {
                        $value = $this->normalize(
                            (string) $node->nodeValue
                        );

                        if ($value !== '') {
                            $segments[] = $value;
                        }
                    }
                }
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($segments === []) {
            $parts = preg_split('/<[^>]+>/u', $html);

            foreach ((array) $parts as $part) {
                $value = $this->normalize(
                    strip_tags((string) $part)
                );

                if ($value !== '') {
                    $segments[] = $value;
                }
            }
        }

        return $segments;
    }
}
