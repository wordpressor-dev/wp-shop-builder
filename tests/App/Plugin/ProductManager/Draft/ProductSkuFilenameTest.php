<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Draft;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\Draft\ProductSkuFilename;

final class ProductSkuFilenameTest extends TestCase
{
    public function testRebuildsCanonicalSkuWhenManualVersionChanges(): void
    {
        $result = ProductSkuFilename::synchronize(
            'themeforest-26350912-aabbe-digital-marketplace-wordpress-theme-5.0.0.zip',
            26350912,
            'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
            '6.2.0'
        );

        self::assertSame(
            'themeforest-26350912-aabbe-digital-marketplace-wordpress-theme-6.2.0.zip',
            $result
        );
    }

    public function testKeepsMatchingCanonicalSku(): void
    {
        $sku = 'themeforest-14058034-education-wordpress-theme-education-wp-5.9.4.zip';

        self::assertSame(
            $sku,
            ProductSkuFilename::synchronize(
                $sku,
                14058034,
                'https://themeforest.net/item/education-wordpress-theme-education-wp/14058034',
                '5.9.4'
            )
        );
    }

    public function testRejectsSkuThatDoesNotBelongToItemAndSalesPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'does not match the ThemeForest Item ID and Sales Page'
        );

        ProductSkuFilename::synchronize(
            'themeforest-999-wrong-theme-6.2.0.zip',
            26350912,
            'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
            '6.2.0'
        );
    }

    public function testBuildsSkuWhenAutofillDidNotProvideOne(): void
    {
        self::assertSame(
            'themeforest-26350912-aabbe-digital-marketplace-wordpress-theme-6.2.0.zip',
            ProductSkuFilename::synchronize(
                '',
                26350912,
                'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
                '6.2.0'
            )
        );
    }
}
