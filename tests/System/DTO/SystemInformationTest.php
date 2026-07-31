<?php

declare(strict_types=1);

namespace WPShop\Tests\System\DTO;

use PHPUnit\Framework\TestCase;
use WPShop\System\DTO\PhpInformation;
use WPShop\System\DTO\ServerInformation;
use WPShop\System\DTO\SystemInformation;
use WPShop\System\DTO\WordPressInformation;
use WPShop\Version\DTO\FrameworkVersion;
use WPShop\Version\DTO\PhpVersion;
use WPShop\Version\DTO\VersionInformation;
use WPShop\Version\DTO\WordPressVersion;

final class SystemInformationTest extends TestCase
{
    public function testStoresImmutableSystemSnapshot(): void
    {
        $versions = new VersionInformation(
            new FrameworkVersion('1.0.0'),
            new PhpVersion('8.3.0'),
            new WordPressVersion('6.8'),
            null
        );
        $php = new PhpInformation('8.3.0', '256M', '64M', '64M', 30, ['Core']);
        $server = new ServerInformation('nginx', 'fpm-fcgi', 'Linux');
        $wordpress = new WordPressInformation('6.8', 'en_US', 'UTC', false, false);

        $information = new SystemInformation($versions, $php, $server, $wordpress);

        self::assertSame($versions, $information->versions);
        self::assertSame($php, $information->php);
        self::assertSame($server, $information->server);
        self::assertSame($wordpress, $information->wordpress);
    }
}
