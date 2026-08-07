<?php

declare(strict_types=1);

namespace WPShop\Publisher\Contracts;

use WPShop\Publisher\ThemeHeader;

interface ThemeHeaderParserInterface
{
    public function parse(string $entryPath): ThemeHeader;
}
