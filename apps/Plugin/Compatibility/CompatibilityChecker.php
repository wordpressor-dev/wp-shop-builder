<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\Compatibility;

final readonly class CompatibilityChecker
{
    public const MINIMUM_PHP_VERSION = '8.3.0';

    public const MINIMUM_WORDPRESS_VERSION = '6.8.0';

    public const MINIMUM_WOOCOMMERCE_VERSION = '9.0.0';

    public function __construct(
        private string $phpVersion,
        private string $wordpressVersion,
        private ?string $wooCommerceVersion
    ) {
    }

    public static function fromRuntime(): self
    {
        global $wp_version;

        $wordpressVersion = is_string($wp_version ?? null)
            ? $wp_version
            : '0.0.0';

        $wooCommerceVersion = null;

        if (defined('WC_VERSION')) {
            $version = constant('WC_VERSION');

            if (is_string($version)) {
                $wooCommerceVersion = $version;
            }
        }

        return new self(
            PHP_VERSION,
            $wordpressVersion,
            $wooCommerceVersion
        );
    }

    public function check(): CompatibilityResult
    {
        $errors = [];

        if (
            version_compare(
                $this->phpVersion,
                self::MINIMUM_PHP_VERSION,
                '<'
            )
        ) {
            $errors[] = sprintf(
                'PHP %s or newer is required; current version is %s.',
                self::MINIMUM_PHP_VERSION,
                $this->phpVersion
            );
        }

        if (
            version_compare(
                $this->wordpressVersion,
                self::MINIMUM_WORDPRESS_VERSION,
                '<'
            )
        ) {
            $errors[] = sprintf(
                'WordPress %s or newer is required; current version is %s.',
                self::MINIMUM_WORDPRESS_VERSION,
                $this->wordpressVersion
            );
        }

        if ($this->wooCommerceVersion === null) {
            $errors[] = 'WooCommerce must be installed and active.';
        } elseif (
            version_compare(
                $this->wooCommerceVersion,
                self::MINIMUM_WOOCOMMERCE_VERSION,
                '<'
            )
        ) {
            $errors[] = sprintf(
                'WooCommerce %s or newer is required; current version is %s.',
                self::MINIMUM_WOOCOMMERCE_VERSION,
                $this->wooCommerceVersion
            );
        }

        return new CompatibilityResult($errors);
    }
}