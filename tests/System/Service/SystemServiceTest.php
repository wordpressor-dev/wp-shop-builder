<?php

declare(strict_types=1);

namespace WPShop\Tests\System\Service;

use PHPUnit\Framework\TestCase;
use WPShop\Environment\PhpEnvironment;
use WPShop\Environment\ServerEnvironment;
use WPShop\Environment\WordPressEnvironment;
use WPShop\System\Service\SystemService;
use WPShop\Version\Service\VersionService;

final class SystemServiceTest extends TestCase
{
    public function testComposesVersionAndEnvironmentInformation(): void
    {
        $php = new PhpEnvironment();
        $server = new ServerEnvironment();
        $wordpress = new WordPressEnvironment();
        $service = new SystemService(
            new VersionService(),
            $php,
            $server,
            $wordpress
        );

        $information = $service->information();

        self::assertSame(PHP_VERSION, $information->versions->php->version);
        self::assertSame($php->version(), $information->php->version);
        self::assertSame($php->extensions(), $information->php->extensions);
        self::assertSame($server->phpSapi(), $information->server->phpSapi);
        self::assertSame($wordpress->timezone(), $information->wordpress->timezone);
    }
}
