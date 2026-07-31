<?php

declare(strict_types=1);

namespace WPShop\Tests\Version\Service;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use WPShop\Core\Framework;
use WPShop\Version\Service\VersionService;

final class VersionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wp_version']);
    }

    public function testReturnsFrameworkPhpAndWordPressVersions(): void
    {
        $GLOBALS['wp_version'] = '6.8.2';

        $information = (new VersionService())->information();

        self::assertSame(Framework::VERSION, $information->framework->version);
        self::assertSame(PHP_VERSION, $information->php->version);
        self::assertSame('6.8.2', $information->wordpress->version);
    }

    public function testReturnsUnavailableOutsideWordPress(): void
    {
        $information = (new VersionService())->information();

        self::assertSame('Unavailable', $information->wordpress->version);
    }

    public function testReturnsNullWithoutWooCommerce(): void
    {
        $information = (new VersionService())->information();

        self::assertNull($information->woocommerce);
    }

    #[RunInSeparateProcess]
    public function testReturnsWooCommerceVersionWhenAvailable(): void
    {
        define('WC_VERSION', '10.1.0');

        $information = (new VersionService())->information();

        self::assertNotNull($information->woocommerce);
        self::assertSame('10.1.0', $information->woocommerce->version);
    }
}
