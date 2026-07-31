<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\CompatibilityNotice;
use WPShop\App\Plugin\Compatibility\CompatibilityResult;

final class CompatibilityNoticeTest extends TestCase
{
    public function testNoticeContainsCompatibilityError(): void
    {
        $result = new CompatibilityResult([
            'WooCommerce must be installed and active.',
        ]);

        $notice = new CompatibilityNotice($result);

        self::assertSame(
            'WP Shop Builder is inactive. WooCommerce must be installed and active.',
            $notice->message()
        );
    }

    public function testNoticeEscapesHtml(): void
    {
        $result = new CompatibilityResult([
            '<strong>Compatibility error</strong>',
        ]);

        $notice = new CompatibilityNotice($result);

        ob_start();
        $notice->render();
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            '&lt;strong&gt;Compatibility error&lt;/strong&gt;',
            $output
        );

        self::assertStringNotContainsString(
            '<strong>Compatibility error</strong>',
            $output
        );
    }
}
