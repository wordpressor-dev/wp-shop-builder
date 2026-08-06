<?php

declare(strict_types=1);

namespace WPShop\Publisher\Resolution;

use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageEntryFilenameResolverInterface;
use WPShop\Publisher\Exception\PackageEntryResolutionFailed;

final readonly class WordPressPackageEntryFilenameResolver implements
    PackageEntryFilenameResolverInterface
{
    public function resolve(Blueprint $blueprint): string
    {
        return match ($blueprint->type()) {
            'plugin' => $blueprint->slug() . '.php',
            'theme' => 'style.css',
            default => throw PackageEntryResolutionFailed
                ::unsupportedBlueprintType(
                    $blueprint->type()
                ),
        };
    }
}
