<?php

declare(strict_types=1);

namespace WPShop\Publisher\Source;

use InvalidArgumentException;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageEntryFilenameResolverInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\Exception\PackageSourceResolutionFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Release\Release;

final readonly class LocalPackageSourceResolver implements
    PackageSourceResolverInterface
{
    private string $root;

    public function __construct(
        string $root,
        private PackageEntryFilenameResolverInterface $entryFilenameResolver
    ) {
        $normalizedRoot = rtrim(
            trim($root),
            '/\\'
        );

        if (
            $normalizedRoot === ''
            || str_contains($normalizedRoot, "\0")
        ) {
            throw new InvalidArgumentException(
                'Local package source root cannot be empty.'
            );
        }

        $this->root = $normalizedRoot;
    }

    public function resolve(
        Blueprint $blueprint,
        Release $release
    ): PackageSource {
        $source = new PackageSource(
            $this->root
                . DIRECTORY_SEPARATOR
                . $blueprint->uuid()
                . DIRECTORY_SEPARATOR
                . $release->id(),
            $blueprint->slug(),
            $this->entryFilenameResolver->resolve($blueprint)
        );

        if (is_link($source->sourceDirectory())) {
            throw PackageSourceResolutionFailed
                ::sourceDirectoryIsSymbolicLink(
                    $source->sourceDirectory()
                );
        }

        if (
            ! is_dir($source->sourceDirectory())
            || ! is_readable($source->sourceDirectory())
        ) {
            throw PackageSourceResolutionFailed
                ::sourceDirectoryUnavailable(
                    $source->sourceDirectory()
                );
        }

        if (is_link($source->entryPath())) {
            throw PackageSourceResolutionFailed
                ::entryFileIsSymbolicLink(
                    $source->entryPath()
                );
        }

        if (
            ! is_file($source->entryPath())
            || ! is_readable($source->entryPath())
        ) {
            throw PackageSourceResolutionFailed
                ::entryFileUnavailable(
                    $source->entryPath()
                );
        }

        return $source;
    }
}
