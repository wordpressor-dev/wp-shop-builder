<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateCandidateClassifier;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateSnapshot;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateSuggestion;

final class ProductUpdateCandidateClassifierTest extends TestCase
{
    public function testSameVersionlessTemplateKitDateIsSame(): void
    {
        self::assertSame(
            'SAME SOURCE DATE',
            ProductUpdateCandidateClassifier::label(
                $this->templateKitSnapshot('2025-12-09'),
                new ProductUpdateSuggestion(
                    '',
                    '2025-12-09',
                    'themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip',
                    'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/TEMPLATES/43194184/themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip'
                )
            )
        );
    }

    public function testOlderVersionlessTemplateKitDateIsAlsoNotAnUpdate(): void
    {
        self::assertSame(
            'SAME SOURCE DATE',
            ProductUpdateCandidateClassifier::label(
                $this->templateKitSnapshot('2025-12-09'),
                new ProductUpdateSuggestion('', '2025-12-08', '', '')
            )
        );
    }

    public function testAdvancedVersionlessTemplateKitDateRequiresReview(): void
    {
        self::assertSame(
            'REVIEW REQUIRED',
            ProductUpdateCandidateClassifier::label(
                $this->templateKitSnapshot('2025-12-09'),
                new ProductUpdateSuggestion('', '2026-01-10', '', '')
            )
        );
    }

    public function testMatchingPublishedVersionRemainsSameVersion(): void
    {
        $snapshot = new ProductUpdateSnapshot(
            5034,
            'publish',
            'Veera – Multipurpose WooCommerce Theme 2.0.0',
            'Veera – Multipurpose WooCommerce Theme',
            22380037,
            '2.0.0',
            '2026-08-20',
            'https://themeforest.net/item/veera-multipurpose-woocommerce-theme/22380037',
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            ''
        );

        self::assertSame(
            'SAME VERSION',
            ProductUpdateCandidateClassifier::label(
                $snapshot,
                new ProductUpdateSuggestion('2.0.0', '2026-08-20', '', '')
            )
        );
    }

    private function templateKitSnapshot(string $date): ProductUpdateSnapshot
    {
        return new ProductUpdateSnapshot(
            5156,
            'publish',
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            'EstateRoof – Roofing Services Elementor Pro Template Kit',
            43194184,
            '',
            $date,
            'https://themeforest.net/item/estateroof-roofing-services-elementor-pro-template-kit/43194184',
            'themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip',
            'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/TEMPLATES/43194184/themeforest-43194184-estateroof-roofing-services-elementor-pro-template-kit.zip'
        );
    }
}
