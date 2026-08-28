<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Update;

use Throwable;
use ZipArchive;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductArchiveVersionInspector
{
    /**
     * @param array<string, mixed> $file
     */
    public function inspect(
        array $file,
        string $productType
    ): ProductArchiveVersionResult {
        $error = isset($file['error'])
            ? (int) $file['error']
            : UPLOAD_ERR_NO_FILE;

        if ($file === [] || $error === UPLOAD_ERR_NO_FILE) {
            return $this->failure('ZIP INSPECTION = FILE NOT SUPPLIED');
        }

        if ($error !== UPLOAD_ERR_OK) {
            return $this->failure('ZIP INSPECTION UPLOAD ERROR = ' . $error);
        }

        $name = $this->string($file['name'] ?? null);
        $tmpName = $this->string($file['tmp_name'] ?? null);

        if (
            $name === ''
            || strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            return $this->failure('ZIP INSPECTION = ZIP FILE REQUIRED');
        }

        if ($tmpName === '' || ! is_file($tmpName)) {
            return $this->failure('ZIP INSPECTION = TEMPORARY FILE MISSING');
        }

        if ($productType === CatalogProductType::TEMPLATE_KIT) {
            return new ProductArchiveVersionResult(
                true,
                '',
                [
                    'ZIP INSPECTION = READY',
                    'ZIP VERSION MODE = VERSIONLESS TEMPLATE KIT',
                    'ZIP VERSION = NOT REQUIRED',
                ]
            );
        }

        if (! class_exists(ZipArchive::class)) {
            return $this->failure('ZIP INSPECTION = ZIP EXTENSION UNAVAILABLE');
        }

        $zip = new ZipArchive();
        $isOpen = false;

        try {
            $opened = $zip->open($tmpName);

            if ($opened !== true) {
                return $this->failure('ZIP INSPECTION = CANNOT OPEN ARCHIVE');
            }

            $isOpen = true;
            $detected = $productType === CatalogProductType::PLUGIN
                ? $this->pluginVersion($zip)
                : $this->themeVersion($zip);

            if ($detected === null) {
                return $this->failure(
                    $productType === CatalogProductType::PLUGIN
                        ? 'ZIP VERSION = PLUGIN HEADER NOT FOUND'
                        : 'ZIP VERSION = THEME STYLE.CSS HEADER NOT FOUND'
                );
            }

            [$version, $source] = $detected;

            if ($version === '') {
                return $this->failure('ZIP VERSION = EMPTY');
            }

            return new ProductArchiveVersionResult(
                true,
                $version,
                [
                    'ZIP INSPECTION = READY',
                    'ZIP VERSION = SOURCE OF TRUTH: ' . $version,
                    'ZIP VERSION SOURCE = ' . $source,
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure(
                'ZIP INSPECTION EXCEPTION = ' . $exception->getMessage()
            );
        } finally {
            if ($isOpen) {
                $zip->close();
            }
        }
    }

    /**
     * @return array{string, string}|null
     */
    private function themeVersion(ZipArchive $zip): ?array
    {
        $candidates = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(str_replace('\\', '/', $name));

            if (
                $normalized === 'style.css'
                || str_ends_with($normalized, '/style.css')
            ) {
                $candidates[] = [$this->pathDepth($normalized), $index, $name];
            }
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left[0] <=> $right[0]
        );

        foreach ($candidates as [, $index, $name]) {
            $header = $zip->getFromIndex((int) $index, 16384);

            if (! is_string($header)) {
                continue;
            }

            $version = $this->headerValue($header, 'Version');

            if ($version !== '') {
                return [$version, (string) $name];
            }
        }

        return null;
    }

    /**
     * @return array{string, string}|null
     */
    private function pluginVersion(ZipArchive $zip): ?array
    {
        $candidates = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(str_replace('\\', '/', $name));

            if (! str_ends_with($normalized, '.php')) {
                continue;
            }

            if (
                str_contains($normalized, '/vendor/')
                || str_contains($normalized, '/node_modules/')
                || str_starts_with($normalized, '__macosx/')
            ) {
                continue;
            }

            $candidates[] = [$this->pathDepth($normalized), $index, $name];
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left[0] <=> $right[0]
        );

        foreach (array_slice($candidates, 0, 80) as [, $index, $name]) {
            $header = $zip->getFromIndex((int) $index, 16384);

            if (! is_string($header)) {
                continue;
            }

            if ($this->headerValue($header, 'Plugin Name') === '') {
                continue;
            }

            $version = $this->headerValue($header, 'Version');

            if ($version !== '') {
                return [$version, (string) $name];
            }
        }

        return null;
    }

    private function headerValue(string $content, string $field): string
    {
        $pattern = '/^[ \t\/*#@]*'
            . preg_quote($field, '/')
            . '\s*:\s*(.+?)\s*$/mi';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return '';
        }

        $value = trim((string) $matches[1]);
        $value = preg_replace('/\s*(?:\*\/)?\s*$/', '', $value);

        return is_string($value) ? trim($value) : '';
    }

    private function pathDepth(string $path): int
    {
        return substr_count(trim($path, '/'), '/');
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function failure(string $message): ProductArchiveVersionResult
    {
        return new ProductArchiveVersionResult(
            false,
            '',
            [
                $message,
                'STOP: ONE-CLICK UPDATE NOT STARTED.',
            ]
        );
    }
}
