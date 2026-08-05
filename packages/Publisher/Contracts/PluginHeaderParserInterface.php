<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\PluginHeader;

interface PluginHeaderParserInterface
{
    public function parse(string $entryPath): PluginHeader;
}
