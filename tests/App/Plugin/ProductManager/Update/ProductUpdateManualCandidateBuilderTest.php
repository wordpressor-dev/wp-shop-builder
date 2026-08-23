<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateManualCandidateBuilder;

final class ProductUpdateManualCandidateBuilderTest extends TestCase
{
    public function testBuildsCanonicalManualCandidate(): void
    {
        $builder = new ProductUpdateManualCandidateBuilder();
        $salesPage = implode('', [
            'https://themeforest.net/item/',
            'aabbe-digital-marketplace-wordpress-theme/26350912',
        ]);
        $currentUrl = implode('', [
            'https://wp-shop.org/wp-content/uploads/',
            'woocommerce_uploads/THEMES/Themeforest/26350912/',
            'themeforest-26350912-aabbe-digital-marketplace-',
            'wordpress-theme-6.2.0.zip',
        ]);

        $suggestion = $builder->build(
            26350912,
            $salesPage,
            '6.3.0',
            $currentUrl
        );

        self::assertSame('6.3.0', $suggestion->version);
        self::assertSame(
            implode('', [
                'themeforest-26350912-aabbe-digital-marketplace-',
                'wordpress-theme-6.3.0.zip',
            ]),
            $suggestion->skuFilename
        );
        self::assertSame(
            implode('', [
                'https://wp-shop.org/wp-content/uploads/',
                'woocommerce_uploads/THEMES/Themeforest/26350912/',
                'themeforest-26350912-aabbe-digital-marketplace-',
                'wordpress-theme-6.3.0.zip',
            ]),
            $suggestion->downloadUrl
        );
    }

    public function testRejectsSalesPageItemMismatch(): void
    {
        $builder = new ProductUpdateManualCandidateBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'ThemeForest Item ID does not match the Sales Page.'
        );

        $builder->build(
            26350912,
            'https://themeforest.net/item/other-theme/999',
            '6.3.0',
            ''
        );
    }
}
