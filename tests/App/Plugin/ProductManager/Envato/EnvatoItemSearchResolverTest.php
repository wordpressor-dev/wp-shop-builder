<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\ProductManager\Envato;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\ProductManager\CatalogProductType;
use WPShop\App\Plugin\ProductManager\Envato\EnvatoItemSearchResolver;

final class EnvatoItemSearchResolverTest extends TestCase
{
    public function testResolvesUniqueExactLeadMatch(): void
    {
        $requestedUrl = '';
        $resolver = new EnvatoItemSearchResolver(
            static function (
                string $url,
                array $headers
            ) use (&$requestedUrl): array {
                $requestedUrl = $url;

                self::assertSame(
                    'Bearer token-123',
                    $headers['Authorization'] ?? ''
                );

                return [
                    'matches' => [
                        [
                            'id' => 7758048,
                            'name' => 'Betheme | Responsive Multipurpose '
                                . 'WordPress & WooCommerce Theme',
                            'url' => 'https://themeforest.net/item/betheme-responsive-multipurpose-wordpress-theme/7758048',
                        ],
                        [
                            'id' => 999999,
                            'name' => 'BeTheme Addons Collection',
                            'url' => 'https://themeforest.net/item/betheme-addons/999999',
                        ],
                    ],
                ];
            }
        );

        $result = $resolver->resolve(
            'Betheme',
            CatalogProductType::THEME,
            'token-123'
        );

        self::assertTrue($result->success);
        self::assertSame(7758048, $result->itemId);
        self::assertSame(100, $result->score);
        self::assertStringContainsString(
            'site=themeforest.net',
            $requestedUrl
        );
        self::assertStringContainsString(
            'term=Betheme',
            $requestedUrl
        );
    }

    public function testRejectsAmbiguousTopMatches(): void
    {
        $resolver = new EnvatoItemSearchResolver(
            static fn (string $url, array $headers): array => [
                'matches' => [
                    [
                        'id' => 1,
                        'name' => 'Bridge | Creative WordPress Theme',
                        'url' => 'https://themeforest.net/item/bridge/1',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Bridge - Multipurpose WordPress Theme',
                        'url' => 'https://themeforest.net/item/bridge-two/2',
                    ],
                ],
            ]
        );

        $result = $resolver->resolve(
            'Bridge',
            CatalogProductType::THEME,
            'token'
        );

        self::assertFalse($result->success);
        self::assertSame(0, $result->itemId);
        self::assertSame(
            'ENVATO AUTO-MATCH = AMBIGUOUS TOP CANDIDATES',
            $result->message
        );
    }

    public function testRejectsLowConfidenceCandidate(): void
    {
        $resolver = new EnvatoItemSearchResolver(
            static fn (string $url, array $headers): array => [
                'matches' => [
                    [
                        'id' => 5,
                        'name' => 'Totally Different WooCommerce Theme',
                        'url' => 'https://themeforest.net/item/different/5',
                    ],
                ],
            ]
        );

        $result = $resolver->resolve(
            'Merchandiser',
            CatalogProductType::THEME,
            'token'
        );

        self::assertFalse($result->success);
        self::assertSame(
            'ENVATO AUTO-MATCH = NO HIGH-CONFIDENCE CANDIDATE',
            $result->message
        );
    }
}
