<?php

declare(strict_types=1);

namespace WPShop\Tests\Version\DTO;

use PHPUnit\Framework\TestCase;
use WPShop\Version\DTO\FrameworkVersion;
use WPShop\Version\DTO\PhpVersion;
use WPShop\Version\DTO\VersionInformation;
use WPShop\Version\DTO\WooCommerceVersion;
use WPShop\Version\DTO\WordPressVersion;

final class VersionInformationTest extends TestCase
{
    public function testStoresTypedVersionInformation(): void
    {
        $framework = new FrameworkVersion('1.0.0');
        $php = new PhpVersion('8.3.0');
        $wordpress = new WordPressVersion('6.8.0');
        $woocommerce = new WooCommerceVersion('10.0.0');

        $information = new VersionInformation(
            $framework,
            $php,
            $wordpress,
            $woocommerce
        );

        self::assertSame($framework, $information->framework);
        self::assertSame($php, $information->php);
        self::assertSame($wordpress, $information->wordpress);
        self::assertSame($woocommerce, $information->woocommerce);
    }
}
