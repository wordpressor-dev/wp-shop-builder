<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Editorial;

final class EnvatoTemplateKitSalesPageExtractor
{
    /**
     * @return array{
     *   templateCount:int,
     *   elementorProRequired:?bool,
     *   responsive:bool,
     *   dragDrop:bool,
     *   globalStyles:bool,
     *   templates:list<string>,
     *   requiredPlugins:list<string>,
     *   helloElementor:bool,
     *   demoImagesLicense:bool,
     *   tags:list<string>
     * }
     */
    public function extract(string $html): array
    {
        $plain = $this->plainText($html);

        $templateCount = 0;
        if (
            preg_match(
                '/\b(\d{1,3})\+\s+(?:pre[-\s]?built\s+)?templates\b/ui',
                $plain,
                $matches
            ) === 1
        ) {
            $templateCount = (int) $matches[1];
        }

        $elementorProRequired = null;
        if (
            preg_match(
                '/elementor\s+pro\s+(?:is\s+)?not\s+required/ui',
                $plain
            ) === 1
            || preg_match(
                '/using\s+free\s+plugins\s*\([^)]*elementor\s+pro\s+is\s+not\s+required/ui',
                $plain
            ) === 1
        ) {
            $elementorProRequired = false;
        } elseif (
            preg_match(
                '/(?:requires?|required)\s+(?:the\s+)?elementor\s+pro/ui',
                $plain
            ) === 1
        ) {
            $elementorProRequired = true;
        }

        return [
            'templateCount' => $templateCount,
            'elementorProRequired' => $elementorProRequired,
            'responsive' => preg_match(
                '/(?:fully\s+responsive|mobile[-\s]?friendly)/ui',
                $plain
            ) === 1,
            'dragDrop' => preg_match(
                '/(?:drag\s*(?:&|and|\+)\s*drop|no[-\s]?code\s+customization)/ui',
                $plain
            ) === 1,
            'globalStyles' => preg_match(
                '/(?:global\s+theme\s+kit\s+style|customize\s+fonts\s+and\s+colors\s+in\s+one\s+place)/ui',
                $plain
            ) === 1,
            'templates' => $this->sectionItems(
                $html,
                '/templates\s+(?:in\s+zip|include(?:d|s)?)\s*:?/ui',
                '/(?:required\s+plugins?|how\s+to\s+use|this\s+template\s+kit\s+contains)/ui'
            ),
            'requiredPlugins' => $this->sectionItems(
                $html,
                '/required\s+plugins?\s*:?/ui',
                '/(?:how\s+to\s+use|this\s+template\s+kit\s+contains|images?\b|setup\b)/ui'
            ),
            'helloElementor' => preg_match(
                '/hello\s+elementor/ui',
                $plain
            ) === 1,
            'demoImagesLicense' => preg_match(
                '/demo\s+images?.{0,180}(?:license|envato\s+elements)/ui',
                $plain
            ) === 1,
            'tags' => $this->pageTags($html),
        ];
    }

    /**
     * @return list<string>
     */
    private function sectionItems(
        string $html,
        string $startPattern,
        string $endPattern
    ): array {
        $start = null;

        if (
            preg_match(
                $startPattern,
                $html,
                $match,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $start = (int) $match[0][1] + strlen((string) $match[0][0]);
        }

        if ($start === null) {
            return [];
        }

        $tail = substr($html, $start);

        $length = strlen($tail);
        if (
            preg_match(
                $endPattern,
                $tail,
                $end,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $length = (int) $end[0][1];
        }

        $section = substr($tail, 0, $length);
        if ($section === '') {
            return [];
        }

        if (
            preg_match_all(
                '/<li\b[^>]*>(.*?)<\/li>/uis',
                $section,
                $matches
            ) === false
        ) {
            return [];
        }

        $items = [];
        foreach ($matches[1] as $item) {
            $item = $this->cleanItem((string) $item);

            if ($item !== '' && mb_strlen($item, 'UTF-8') <= 100) {
                $items[] = $item;
            }
        }

        return array_values(array_unique(array_slice($items, 0, 30)));
    }

    /**
     * @return list<string>
     */
    private function pageTags(string $html): array
    {
        $tags = [];

        if (
            preg_match_all(
                '~href=["\'](?:https?://(?:www\.)?themeforest\.net)?/search/([^"\'?#]+)~ui',
                $html,
                $matches
            ) !== false
        ) {
            foreach ($matches[1] as $value) {
                $tag = rawurldecode((string) $value);
                $tag = str_replace('+', ' ', $tag);
                $tag = trim((string) preg_replace('/\s+/u', ' ', $tag));

                if (
                    $tag !== ''
                    && mb_strlen($tag, 'UTF-8') <= 60
                ) {
                    $tags[] = mb_strtolower($tag, 'UTF-8');
                }
            }
        }

        return array_values(array_unique($tags));
    }

    private function cleanItem(string $value): string
    {
        $value = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        return trim($value, " \t\n\r\0\x0B-–—:;,. ");
    }

    private function plainText(string $html): string
    {
        $html = (string) preg_replace(
            '/<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>/uis',
            ' ',
            $html
        );
        $html = (string) preg_replace(
            '/<[^>]+>/u',
            ' ',
            $html
        );
        $text = html_entity_decode(
            $html,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
