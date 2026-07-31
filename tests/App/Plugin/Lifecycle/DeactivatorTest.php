<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Lifecycle;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Lifecycle\Deactivator;

final class DeactivatorTest extends TestCase
{
    public function testPluginCanBeDeactivated(): void
    {
        $flushedWith = null;

        $flushRewriteRules = static function (
            bool $hard
        ) use (&$flushedWith): void {
            $flushedWith = $hard;
        };

        $deactivator = new Deactivator(
            $flushRewriteRules
        );

        $deactivator->deactivate();

        self::assertFalse($flushedWith);
    }
}
