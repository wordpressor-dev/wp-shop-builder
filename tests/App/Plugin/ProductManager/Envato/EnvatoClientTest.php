<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Envato;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoClient;

final class EnvatoClientTest extends TestCase
{
    public function testFetchesItemAndVersionEndpoints(): void
    {
        $requests = [];

        $client = new EnvatoClient(
            static function (
                string $url,
                array $headers
            ) use (&$requests): array {
                $requests[] = [$url, $headers];

                if (str_contains($url, 'item-version')) {
                    return ['version' => '6.2.0'];
                }

                return [
                    'id' => 26350912,
                    'name' => 'Aabbe - Digital Marketplace WordPress Theme',
                    'url' => 'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
                    'author_username' => 'QuomodoTheme',
                    'updated_at' => '2025-04-20T20:51:36+10:00',
                ];
            }
        );

        $item = $client->fetch(
            'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
            'secret-token'
        );

        self::assertCount(2, $requests);
        self::assertStringContainsString(
            'catalog/item?id=26350912',
            $requests[0][0]
        );
        self::assertStringContainsString(
            'catalog/item-version?id=26350912',
            $requests[1][0]
        );
        self::assertSame(
            'Bearer secret-token',
            $requests[0][1]['Authorization']
        );
        self::assertSame('6.2.0', $item->version);
    }

    public function testFallsBackToItemMetadataWhenVersionRequestFails(): void
    {
        $client = new EnvatoClient(
            static function (string $url): array {
                if (str_contains($url, 'item-version')) {
                    throw new RuntimeException('Version endpoint unavailable.');
                }

                return [
                    'id' => 26350912,
                    'name' => 'Aabbe - Digital Marketplace WordPress Theme',
                    'url' => 'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
                    'wordpress_theme_metadata' => [
                        'version' => '5.0.0',
                    ],
                ];
            }
        );

        $item = $client->fetch(
            'https://themeforest.net/item/aabbe-digital-marketplace-wordpress-theme/26350912',
            'token'
        );

        self::assertSame('5.0.0', $item->version);
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Envato personal token is empty.'
        );

        (new EnvatoClient(static fn(): array => []))
            ->fetch(
                'https://themeforest.net/item/theme/26350912',
                ''
            );
    }
}
