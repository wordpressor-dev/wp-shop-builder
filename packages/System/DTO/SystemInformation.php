<?php

declare(strict_types=1);

namespace WPShop\System\DTO;

use WPShop\Version\DTO\VersionInformation;

final readonly class SystemInformation
{
    public function __construct(
        public VersionInformation $versions,
        public PhpInformation $php,
        public ServerInformation $server,
        public WordPressInformation $wordpress
    ) {
    }
}
