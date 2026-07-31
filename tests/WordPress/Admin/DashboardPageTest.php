<?php

declare(strict_types=1);

namespace WPShop\Tests\WordPress\Admin;

use PHPUnit\Framework\TestCase;
use WPShop\Core\Container\Container;
use WPShop\Core\Kernel\Kernel;
use WPShop\WordPress\Admin\DashboardPage;
use WPShop\WordPress\Application\Application;
use WPShop\WordPress\Plugin\PluginManager;

final class DashboardPageTest extends TestCase
{
    public function testProvidesPageMetadata(): void
    {
        $page = $this->page();

        self::assertSame('wp-shop-builder', $page->slug());
        self::assertSame('WP Shop Builder', $page->title());
        self::assertSame('manage_options', $page->capability());
    }

    public function testRendersEnvironmentInformation(): void
    {
        $GLOBALS['wp_version'] = '6.8-test';
        $page = $this->page();

        ob_start();
        $page->render();
        $output = (string) ob_get_clean();

        self::assertStringContainsString('WP Shop Builder', $output);
        self::assertStringContainsString('0.1.0-dev', $output);
        self::assertStringContainsString(PHP_VERSION, $output);
        self::assertStringContainsString('6.8-test', $output);
        self::assertStringContainsString('Not installed', $output);

        unset($GLOBALS['wp_version']);
    }

    private function page(): DashboardPage
    {
        $container = new Container();
        $application = new Application(
            $container,
            new Kernel(),
            new PluginManager()
        );

        return new DashboardPage($application);
    }
}
