<?php

declare(strict_types=1);

namespace WPShop\Version\DTO;

final readonly class VersionInformation
{
    public function __construct(
        public FrameworkVersion $framework,
        public PhpVersion $php,
        public WordPressVersion $wordpress,
        public ?WooCommerceVersion $woocommerce
    ) {
    }
}
