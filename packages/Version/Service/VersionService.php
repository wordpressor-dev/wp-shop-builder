<?php

declare(strict_types=1);

namespace WPShop\Version\Service;

use WPShop\Core\Framework;
use WPShop\Version\Contracts\VersionServiceInterface;
use WPShop\Version\DTO\FrameworkVersion;
use WPShop\Version\DTO\PhpVersion;
use WPShop\Version\DTO\VersionInformation;
use WPShop\Version\DTO\WooCommerceVersion;
use WPShop\Version\DTO\WordPressVersion;

final class VersionService implements VersionServiceInterface
{
    public function information(): VersionInformation
    {
        return new VersionInformation(
            framework: new FrameworkVersion(Framework::VERSION),
            php: new PhpVersion(PHP_VERSION),
            wordpress: new WordPressVersion($this->wordpressVersion()),
            woocommerce: $this->woocommerceVersion()
        );
    }

    private function wordpressVersion(): string
    {
        $version = $GLOBALS['wp_version'] ?? null;

        return is_string($version) && $version !== ''
            ? $version
            : 'Unavailable';
    }

    private function woocommerceVersion(): ?WooCommerceVersion
    {
        if (!defined('WC_VERSION')) {
            return null;
        }

        $version = constant('WC_VERSION');

        if (!is_string($version) || $version === '') {
            return null;
        }

        return new WooCommerceVersion($version);
    }
}
