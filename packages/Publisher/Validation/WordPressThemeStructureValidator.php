<?php

declare(strict_types=1);

namespace WPShop\Publisher\Validation;

use WPShop\Publisher\Contracts\ThemeStructureValidatorInterface;
use WPShop\Publisher\Exception\ThemeStructureValidationFailed;
use WPShop\Publisher\PackageSource;

final readonly class WordPressThemeStructureValidator implements
    ThemeStructureValidatorInterface
{
    /**
     * @var list<string>
     */
    private const array ENTRY_POINTS = [
        'index.php',
        'templates/index.html',
    ];

    public function validate(PackageSource $source): void
    {
        $hasEntryPoint = false;

        foreach (self::ENTRY_POINTS as $relativePath) {
            if (
                $this->validateEntryPoint(
                    $source->sourceDirectory(),
                    $relativePath
                )
            ) {
                $hasEntryPoint = true;
            }
        }

        if (! $hasEntryPoint) {
            throw ThemeStructureValidationFailed
                ::missingEntryPoint(
                    $source->sourceDirectory()
                );
        }
    }

    private function validateEntryPoint(
        string $sourceDirectory,
        string $relativePath
    ): bool {
        $path = $sourceDirectory;

        foreach (explode('/', $relativePath) as $segment) {
            $path .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($path)) {
                throw ThemeStructureValidationFailed
                    ::entryIsSymbolicLink($relativePath);
            }
        }

        if (! file_exists($path)) {
            return false;
        }

        if (
            ! is_file($path)
            || ! is_readable($path)
        ) {
            throw ThemeStructureValidationFailed
                ::entryUnavailable($relativePath);
        }

        return true;
    }
}
