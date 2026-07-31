<?php

declare(strict_types=1);

namespace WPShop\Tests\Environment;

use PHPUnit\Framework\TestCase;
use WPShop\Environment\WordPressEnvironment;

final class WordPressEnvironmentTest extends TestCase
{
    public function testProvidesSafeFallbacksWithoutWordPress(): void
    {
        $environment = new WordPressEnvironment();

        self::assertSame('Unavailable', $environment->version());
        self::assertSame('Unavailable', $environment->locale());
        self::assertSame(date_default_timezone_get(), $environment->timezone());
        self::assertFalse($environment->isMultisite());
        self::assertFalse($environment->isDebug());
    }
}
