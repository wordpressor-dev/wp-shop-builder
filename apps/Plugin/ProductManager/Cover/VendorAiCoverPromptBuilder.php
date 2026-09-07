<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Cover;

use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

final class VendorAiCoverPromptBuilder
{
    public const VERSION = 'vendor-ai-cover-v1';

    public function build(ProductDraftData $data): string
    {
        $title = trim($data->title());
        $purpose = $this->purpose(
            $title,
            $data->enShortDescription,
            $data->productType
        );
        $type = strtolower(trim($data->productType)) === 'theme'
            ? 'WordPress theme'
            : 'WordPress plugin';
        $developer = trim($data->developer);

        return implode(
            "\n",
            [
                'Create exactly one premium commercial product cover for the WP Shop catalog.',
                'The source product is a ' . $type . '.',
                'Canvas requested by the API is 1536x1024, but the final catalog cover will be center-cropped to 590x300 (59:30). Keep ALL important text, logos, UI, and product artwork inside the central horizontal crop-safe band; leave generous decorative-only space at the top and bottom.',
                'Use a polished modern SaaS / WordPress product-marketing aesthetic: clean vector + UI mockup composition, crisp typography, rich but controlled gradients, premium depth, no photo-realistic people.',
                'Layout: product title large on the left; one short functional purpose directly below; one strong product-specific illustration or believable UI/product visual on the right. The right side should visually communicate what the product does.',
                'Use a product-appropriate accent palette while keeping the overall family consistent across WP Shop Vendor products.',
                'READABLE TEXT RULE: render only the exact title and exact purpose supplied below. Do not add feature lists, badges, pricing, versions, URLs, watermarks, tiny UI copy, or any other readable text.',
                'If you are uncertain about an official logo, do not invent a fake trademark. Prefer abstract product-relevant iconography or a clean interface mockup instead.',
                'Do not include the words "WP Shop", "Premium Plugin", "Premium Theme", or "WordPress plugin/theme" in the image unless they are part of the exact product title.',
                'Exact title: ' . $title,
                'Exact purpose: ' . $purpose,
                'Developer context (visual guidance only; do not render as text): '
                    . ($developer !== '' ? $developer : 'not provided'),
            ]
        );
    }

    public function purposeFor(ProductDraftData $data): string
    {
        return $this->purpose(
            $data->title(),
            $data->enShortDescription,
            $data->productType
        );
    }

    private function purpose(
        string $title,
        string $english,
        string $productType
    ): string {
        $english = $this->plainText($english);
        $haystack = mb_strtolower(
            trim($title . ' ' . $english),
            'UTF-8'
        );

        $titleLower = mb_strtolower(trim($title), 'UTF-8');

        if (
            str_contains($titleLower, 'elementor')
            && ! str_contains($titleLower, 'addon')
            && ! str_contains($titleLower, 'add-on')
        ) {
            return 'Visual page builder for WordPress';
        }

        $rules = [
            '/ajax.+search|search.+woocommerce/u' => 'AJAX product search for WooCommerce',
            '/multilingual|translation|translate/u' => 'Multilingual translation for WordPress',
            '/seo|search engine optimization/u' => 'SEO optimization for WordPress',
            '/page builder|website builder|visual builder/u' => 'Visual page builder for WordPress',
            '/woocommerce.+builder|builder.+woocommerce/u' => 'WooCommerce page builder',
            '/security|firewall|malware/u' => 'WordPress security and protection',
            '/backup|restore/u' => 'WordPress backup and restore',
            '/form|forms/u' => 'Form builder for WordPress',
            '/gallery|portfolio/u' => 'Gallery and portfolio builder',
            '/filter|faceted/u' => 'Product filtering for WooCommerce',
            '/currency/u' => 'Currency switcher for WooCommerce',
            '/table/u' => 'Product tables for WooCommerce',
            '/dark mode/u' => 'Dark mode for WordPress',
            '/cache|performance|accelerat/u' => 'WordPress performance optimization',
            '/analytics|statistics/u' => 'Analytics and insights for WordPress',
            '/email|newsletter/u' => 'Email marketing for WordPress',
        ];

        foreach ($rules as $pattern => $purpose) {
            if (preg_match($pattern, $haystack) === 1) {
                return $purpose;
            }
        }

        $clean = trim($english);

        if ($clean !== '' && trim($title) !== '') {
            $clean = preg_replace(
                '/^' . preg_quote(trim($title), '/') . '\s*(?:[-—:]+|is\s+(?:an?\s+)?)?/iu',
                '',
                $clean
            ) ?? $clean;
        }

        $clean = trim($clean, " \t\n\r\0\x0B-—:;,.");

        if ($clean !== '') {
            $parts = preg_split(
                '/(?:[.;]|\s+[—–]\s+|,\s+)/u',
                $clean,
                2
            );
            $first = is_array($parts)
                ? trim((string) $parts[0])
                : $clean;

            if ($first !== '') {
                return $this->shorten($first, 62);
            }
        }

        return strtolower(trim($productType)) === 'theme'
            ? 'Premium WordPress theme'
            : 'Premium WordPress plugin';
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $value = preg_replace('/\s+/u', ' ', $value);

        return is_string($value) ? trim($value) : '';
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        $cut = mb_substr(
            $value,
            0,
            max(1, $limit - 1),
            'UTF-8'
        );
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($space !== false && $space > 24) {
            $cut = mb_substr(
                $cut,
                0,
                $space,
                'UTF-8'
            );
        }

        return rtrim($cut, " \t\n\r\0\x0B,.;:-") . '…';
    }
}
