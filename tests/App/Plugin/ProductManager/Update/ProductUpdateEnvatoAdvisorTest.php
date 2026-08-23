<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Update;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItem;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateEnvatoAdvisor;
use WPShop\App\Plugin\ProductManager\Update\ProductUpdateSnapshot;

final class ProductUpdateEnvatoAdvisorTest extends TestCase
{
    public function testBuildsCanonicalUpdateSuggestion(): void
    {
        $client = $this->createMock(EnvatoClientInterface::class);
        $client->expects(self::once())
            ->method('fetch')
            ->with(
                'https://themeforest.net/item/veera-multipurpose-woocommerce-theme/22380037',
                'token'
            )
            ->willReturn($this->item());

        $advisor = new ProductUpdateEnvatoAdvisor($client);
        $suggestion = $advisor->suggest(
            $this->snapshot(),
            'token'
        );

        self::assertSame('2.0.0', $suggestion->version);
        self::assertSame('2026-08-20', $suggestion->updateDate);
        self::assertSame(
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            $suggestion->skuFilename
        );
        self::assertSame(
            'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/THEMES/Themeforest/22380037/themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            $suggestion->downloadUrl
        );
    }

    public function testRejectsEnvatoItemMismatch(): void
    {
        $client = $this->createMock(EnvatoClientInterface::class);
        $client->method('fetch')->willReturn(
            new EnvatoItem(
                999,
                'Other Theme',
                'other-theme',
                '2.0.0',
                '2026-08-20',
                'Vendor',
                'https://themeforest.net/item/other-theme/999',
                0,
                null,
                [],
                'themeforest-999-other-theme-2.0.0.zip',
                []
            )
        );

        $advisor = new ProductUpdateEnvatoAdvisor($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Envato Item ID does not match the loaded product.'
        );

        $advisor->suggest($this->snapshot(), 'token');
    }

    private function snapshot(): ProductUpdateSnapshot
    {
        return new ProductUpdateSnapshot(
            5034,
            'publish',
            'Veera – Multipurpose WooCommerce Theme 1.9.0',
            'Veera – Multipurpose WooCommerce Theme',
            22380037,
            '1.9.0',
            '2026-05-13',
            'https://themeforest.net/item/veera-multipurpose-woocommerce-theme/22380037',
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-1.9.0.zip',
            'https://wp-shop.org/wp-content/uploads/woocommerce_uploads/THEMES/Themeforest/22380037/themeforest-22380037-veera-multipurpose-woocommerce-theme-1.9.0.zip'
        );
    }

    private function item(): EnvatoItem
    {
        return new EnvatoItem(
            22380037,
            'Veera – Multipurpose WooCommerce Theme',
            'veera',
            '2.0.0',
            '2026-08-20',
            'LA-Studio',
            'https://themeforest.net/item/veera-multipurpose-woocommerce-theme/22380037',
            100,
            '2018-01-01',
            [],
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-2.0.0.zip',
            []
        );
    }
}
