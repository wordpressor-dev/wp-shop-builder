<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Compatibility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Compatibility\CompatibilityChecker;

final class CompatibilityCheckerTest extends TestCase
{
    public function testCompatibleEnvironmentPasses(): void
    {
        $checker = new CompatibilityChecker(
            '8.3.6',
            '6.8.2',
            '9.1.0'
        );

        $result = $checker->check();

        self::assertTrue($result->isCompatible());
        self::assertSame([], $result->errors());
        self::assertSame('', $result->message());
    }

    #[DataProvider('incompatibleEnvironmentProvider')]
    public function testIncompatibleEnvironmentReturnsError(
        string $phpVersion,
        string $wordpressVersion,
        ?string $wooCommerceVersion,
        string $expectedError
    ): void {
        $checker = new CompatibilityChecker(
            $phpVersion,
            $wordpressVersion,
            $wooCommerceVersion
        );

        $result = $checker->check();

        self::assertFalse($result->isCompatible());
        self::assertContains(
            $expectedError,
            $result->errors()
        );
    }

    /**
     * @return array<string, array{string, string, string|null, string}>
     */
    public static function incompatibleEnvironmentProvider(): array
    {
        return [
            'old PHP' => [
                '8.2.9',
                '6.8.0',
                '9.0.0',
                'PHP 8.3.0 or newer is required; current version is 8.2.9.',
            ],
            'old WordPress' => [
                '8.3.0',
                '6.7.2',
                '9.0.0',
                'WordPress 6.8.0 or newer is required; current version is 6.7.2.',
            ],
            'missing WooCommerce' => [
                '8.3.0',
                '6.8.0',
                null,
                'WooCommerce must be installed and active.',
            ],
            'old WooCommerce' => [
                '8.3.0',
                '6.8.0',
                '8.9.3',
                'WooCommerce 9.0.0 or newer is required; current version is 8.9.3.',
            ],
        ];
    }
}
