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
                $this->salesPage(),
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
            $this->downloadUrl('2.0.0'),
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

    public function testBlocksOlderEnvatoVersion(): void
    {
        $client = $this->createMock(EnvatoClientInterface::class);
        $client->method('fetch')->willReturn(
            $this->item('5.0.0', '2025-04-20')
        );
        $advisor = new ProductUpdateEnvatoAdvisor($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Envato version 5.0.0 is older than current version 6.2.0; '
            . 'downgrade suggestion blocked.'
        );

        $advisor->suggest(
            $this->snapshot('6.2.0'),
            'token'
        );
    }

    private function snapshot(
        string $version = '1.9.0'
    ): ProductUpdateSnapshot {
        return new ProductUpdateSnapshot(
            5034,
            'publish',
            'Veera – Multipurpose WooCommerce Theme ' . $version,
            'Veera – Multipurpose WooCommerce Theme',
            22380037,
            $version,
            '2026-05-13',
            $this->salesPage(),
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-'
                . $version
                . '.zip',
            $this->downloadUrl($version)
        );
    }

    private function item(
        string $version = '2.0.0',
        string $date = '2026-08-20'
    ): EnvatoItem {
        return new EnvatoItem(
            22380037,
            'Veera – Multipurpose WooCommerce Theme',
            'veera',
            $version,
            $date,
            'LA-Studio',
            $this->salesPage(),
            100,
            '2018-01-01',
            [],
            'themeforest-22380037-veera-multipurpose-woocommerce-theme-'
                . $version
                . '.zip',
            []
        );
    }

    private function salesPage(): string
    {
        return 'https://themeforest.net/item/'
            . 'veera-multipurpose-woocommerce-theme/22380037';
    }

    private function downloadUrl(string $version): string
    {
        return 'https://wp-shop.org/wp-content/uploads/'
            . 'woocommerce_uploads/THEMES/Themeforest/22380037/'
            . 'themeforest-22380037-veera-multipurpose-woocommerce-theme-'
            . $version
            . '.zip';
    }
}
