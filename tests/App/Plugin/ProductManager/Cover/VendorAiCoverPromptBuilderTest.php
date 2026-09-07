<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Cover;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Cover\VendorAiCoverPromptBuilder;
use WPShop\App\Plugin\ProductManager\Draft\ProductDraftData;

final class VendorAiCoverPromptBuilderTest extends TestCase
{
    public function testBuildsMinimalProductSpecificPrompt(): void
    {
        $builder = new VendorAiCoverPromptBuilder();
        $data = new ProductDraftData(
            'Elementor Pro',
            'elementor-pro',
            0,
            '3.31.0',
            '2026-09-07',
            'Elementor',
            '249',
            'https://elementor.com/',
            'elementor-pro-3.31.0.zip',
            'https://example.test/elementor-pro.zip',
            0,
            [],
            'RU short',
            'RU long',
            'RU meta',
            'Elementor Pro is a professional visual WordPress builder with Theme Builder and dynamic content.',
            'EN long',
            'EN meta',
            '',
            false,
            false,
            false,
            'plugin'
        );

        $prompt = $builder->build($data);

        self::assertStringContainsString(
            'Exact title: Elementor Pro',
            $prompt
        );
        self::assertStringContainsString(
            'Exact purpose: Visual page builder for WordPress',
            $prompt
        );
        self::assertStringContainsString(
            'render only the exact title and exact purpose',
            $prompt
        );
        self::assertSame(
            'Visual page builder for WordPress',
            $builder->purposeFor($data)
        );
    }
}
