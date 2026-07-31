<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Lifecycle;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Compatibility\CompatibilityChecker;
use WPShop\App\Plugin\Exception\IncompatibleEnvironment;
use WPShop\App\Plugin\Lifecycle\Activator;

final class ActivatorTest extends TestCase
{
    public function testCompatibleEnvironmentCanBeActivated(): void
    {
        $flushedWith = null;

        $flushRewriteRules = static function (
            bool $hard
        ) use (&$flushedWith): void {
            $flushedWith = $hard;
        };

        $checker = new CompatibilityChecker(
            '8.3.6',
            '6.8.2',
            '9.1.0'
        );

        $activator = new Activator(
            $checker,
            $flushRewriteRules
        );

        $activator->activate();

        self::assertFalse($flushedWith);
    }

    public function testIncompatibleEnvironmentCannotBeActivated(): void
    {
        $checker = new CompatibilityChecker(
            '8.3.6',
            '6.8.2',
            null
        );

        $this->expectException(
            IncompatibleEnvironment::class
        );

        $this->expectExceptionMessage(
            'WooCommerce must be installed and active.'
        );

        (new Activator($checker))->activate();
    }
}
