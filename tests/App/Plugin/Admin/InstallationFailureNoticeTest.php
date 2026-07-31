<?php

declare(strict_types=1);

namespace WPShop\Tests\App\Plugin\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\App\Plugin\Admin\InstallationFailureNotice;
use WPShop\App\Plugin\Installation\Exception\InstallationFailed;

final class InstallationFailureNoticeTest extends TestCase
{
    public function testNoticeContainsInstallationError(): void
    {
        $exception = InstallationFailed::stateWrite(
            '0.2.0'
        );

        $notice = new InstallationFailureNotice(
            $exception
        );

        self::assertSame(
            'WP Shop Builder could not complete installation or update. '
            . 'Unable to save installed plugin version 0.2.0.',
            $notice->message()
        );
    }

    public function testNoticeEscapesHtml(): void
    {
        $exception = new InstallationFailed(
            '<strong>Migration failed</strong>'
        );

        $notice = new InstallationFailureNotice(
            $exception
        );

        ob_start();
        $notice->render();
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            '&lt;strong&gt;Migration failed&lt;/strong&gt;',
            $output
        );

        self::assertStringNotContainsString(
            '<strong>Migration failed</strong>',
            $output
        );
    }
}
