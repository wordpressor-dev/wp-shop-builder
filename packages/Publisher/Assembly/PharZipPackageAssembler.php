<?php

declare(strict_types=1);

namespace WPShop\Publisher\Assembly;

use InvalidArgumentException;
use Phar;
use PharData;
use Throwable;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Exception\PackageAssemblyFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PublicationArtifact;
use WPShop\Release\Release;

final readonly class PharZipPackageAssembler implements
    PackageAssemblerInterface
{
    private const string FILENAME = 'package.zip';

    private const string MEDIA_TYPE = 'application/zip';

    private string $workspaceRoot;

    public function __construct(string $workspaceRoot)
    {
        $normalizedRoot = rtrim(
            trim($workspaceRoot),
            '/\\'
        );

        if (
            $normalizedRoot === ''
            || str_contains($normalizedRoot, "\0")
        ) {
            throw new InvalidArgumentException(
                'Package workspace root cannot be empty.'
            );
        }

        $this->workspaceRoot = $normalizedRoot;
    }

    public function assemble(
        Blueprint $blueprint,
        Release $release,
        PackageSource $source
    ): PublicationArtifact {
        $this->assertSourceDirectory(
            $source->sourceDirectory()
        );

        $targetDirectory = $this->workspaceRoot
            . DIRECTORY_SEPARATOR
            . $blueprint->uuid()
            . DIRECTORY_SEPARATOR
            . $release->id();

        $targetPath = $targetDirectory
            . DIRECTORY_SEPARATOR
            . self::FILENAME;

        if (file_exists($targetPath)) {
            throw PackageAssemblyFailed::targetAlreadyExists(
                $targetPath
            );
        }

        /**
         * @var list<array{
         *     type: 'directory'|'file',
         *     source: string,
         *     relative: string
         * }> $entries
         */
        $entries = [];

        $this->collectEntries(
            $source->sourceDirectory(),
            '',
            $entries
        );

        usort(
            $entries,
            static fn(array $left, array $right): int =>
                strcmp(
                    $left['relative'],
                    $right['relative']
                )
        );

        $this->createDirectory($targetDirectory);

        $archive = null;

        try {
            $archive = new PharData(
                $targetPath,
                0,
                null,
                Phar::ZIP
            );

            foreach ($entries as $entry) {
                $archivePath = $source->archiveRoot()
                    . '/'
                    . $entry['relative'];

                if ($entry['type'] === 'directory') {
                    $archive->addEmptyDir($archivePath);

                    continue;
                }

                $archive->addFile(
                    $entry['source'],
                    $archivePath
                );
            }

            unset($archive);

            if (! is_file($targetPath)) {
                throw PackageAssemblyFailed
                    ::archiveCreationFailed(
                        $targetPath,
                        new \RuntimeException(
                            'The archive file was not created.'
                        )
                    );
            }
        } catch (Throwable $failure) {
            unset($archive);

            if (
                file_exists($targetPath)
                && ! @unlink($targetPath)
            ) {
                throw PackageAssemblyFailed
                    ::partialCleanupFailed(
                        $targetPath,
                        $failure
                    );
            }

            if ($failure instanceof PackageAssemblyFailed) {
                throw $failure;
            }

            throw PackageAssemblyFailed
                ::archiveCreationFailed(
                    $targetPath,
                    $failure
                );
        }

        return new PublicationArtifact(
            $targetPath,
            self::FILENAME,
            self::MEDIA_TYPE
        );
    }

    private function assertSourceDirectory(
        string $directory
    ): void {
        if (is_link($directory)) {
            throw PackageAssemblyFailed
                ::symbolicLinkNotAllowed($directory);
        }

        if (
            ! is_dir($directory)
            || ! is_readable($directory)
        ) {
            throw PackageAssemblyFailed
                ::sourceDirectoryUnavailable($directory);
        }
    }

    /**
     * @param list<array{
     *     type: 'directory'|'file',
     *     source: string,
     *     relative: string
     * }> $entries
     */
    private function collectEntries(
        string $directory,
        string $relativeDirectory,
        array &$entries
    ): void {
        $names = @scandir(
            $directory,
            SCANDIR_SORT_ASCENDING
        );

        if (! is_array($names)) {
            throw PackageAssemblyFailed
                ::sourceEntryUnreadable($directory);
        }

        $childCount = 0;

        foreach ($names as $name) {
            if (in_array($name, ['.', '..'], true)) {
                continue;
            }

            $childCount++;

            $path = $directory
                . DIRECTORY_SEPARATOR
                . $name;

            $relativePath = $relativeDirectory === ''
                ? $name
                : $relativeDirectory . '/' . $name;

            if (is_link($path)) {
                throw PackageAssemblyFailed
                    ::symbolicLinkNotAllowed($relativePath);
            }

            if (is_dir($path)) {
                if (! is_readable($path)) {
                    throw PackageAssemblyFailed
                        ::sourceEntryUnreadable(
                            $relativePath
                        );
                }

                $this->collectEntries(
                    $path,
                    $relativePath,
                    $entries
                );

                continue;
            }

            if (is_file($path)) {
                if (! is_readable($path)) {
                    throw PackageAssemblyFailed
                        ::sourceEntryUnreadable(
                            $relativePath
                        );
                }

                $entries[] = [
                    'type' => 'file',
                    'source' => $path,
                    'relative' => $relativePath,
                ];

                continue;
            }

            throw PackageAssemblyFailed
                ::unsupportedSourceEntry($relativePath);
        }

        if (
            $relativeDirectory !== ''
            && $childCount === 0
        ) {
            $entries[] = [
                'type' => 'directory',
                'source' => $directory,
                'relative' => $relativeDirectory,
            ];
        }
    }

    private function createDirectory(string $directory): void
    {
        if (
            ! is_dir($directory)
            && ! @mkdir(
                $directory,
                0775,
                true
            )
            && ! is_dir($directory)
        ) {
            throw PackageAssemblyFailed
                ::directoryCreationFailed($directory);
        }
    }
}
