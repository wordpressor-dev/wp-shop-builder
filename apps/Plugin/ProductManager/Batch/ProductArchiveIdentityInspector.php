<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Throwable;
use ZipArchive;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductArchiveIdentityInspector
{
    public function inspect(string $path, string $filename): ProductArchiveIdentityResult
    {
        if (! class_exists(ZipArchive::class) || ! is_file($path)) {
            return $this->failure();
        }

        $zip = new ZipArchive();
        $opened = false;

        try {
            if ($zip->open($path) !== true) {
                return $this->failure();
            }

            $opened = true;
            $theme = $this->themeIdentity($zip);

            if ($theme !== null) {
                return new ProductArchiveIdentityResult(
                    true,
                    CatalogProductType::THEME,
                    $theme['name'],
                    $theme['version'],
                    $theme['source'],
                    $theme['developer'],
                    $theme['productUrl']
                );
            }

            $plugin = $this->pluginIdentity($zip);

            if ($plugin !== null) {
                return new ProductArchiveIdentityResult(
                    true,
                    CatalogProductType::PLUGIN,
                    $plugin['name'],
                    $plugin['version'],
                    $plugin['source'],
                    $plugin['developer'],
                    $plugin['productUrl']
                );
            }

            if ($this->looksLikeTemplateKit($zip)) {
                $name = trim((string) pathinfo($filename, PATHINFO_FILENAME));

                return new ProductArchiveIdentityResult(
                    true,
                    CatalogProductType::TEMPLATE_KIT,
                    $name,
                    '',
                    'template-kit-json-structure'
                );
            }
        } catch (Throwable) {
            return $this->failure();
        } finally {
            if ($opened) {
                $zip->close();
            }
        }

        return $this->failure();
    }

    /**
     * @return array{
     *   name: string,
     *   version: string,
     *   source: string,
     *   developer: string,
     *   productUrl: string
     * }|null
     */
    private function themeIdentity(ZipArchive $zip): ?array
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
                $candidates[] = [
                    'depth' => substr_count(trim($normalized, '/'), '/'),
                    'index' => $index,
                    'source' => $name,
                ];
            }
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left['depth'] <=> $right['depth']
        );

        foreach ($candidates as $candidate) {
            $header = $zip->getFromIndex((int) $candidate['index'], 16384);

            if (! is_string($header)) {
                continue;
            }

            $name = $this->headerValue($header, 'Theme Name');

            if ($name === '') {
                continue;
            }

            return [
                'name' => $name,
                'version' => $this->headerValue($header, 'Version'),
                'source' => (string) $candidate['source'],
                'developer' => $this->headerValue($header, 'Author'),
                'productUrl' => $this->firstValidUrl([
                    $this->headerValue($header, 'Theme URI'),
                    $this->headerValue($header, 'Author URI'),
                ]),
            ];
        }

        return null;
    }

    /**
     * @return array{
     *   name: string,
     *   version: string,
     *   source: string,
     *   developer: string,
     *   productUrl: string
     * }|null
     */
    private function pluginIdentity(ZipArchive $zip): ?array
    {
        $candidates = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(str_replace('\\', '/', $name));

            if (
                ! str_ends_with($normalized, '.php')
                || str_contains($normalized, '/vendor/')
                || str_contains($normalized, '/node_modules/')
                || str_starts_with($normalized, '__macosx/')
            ) {
                continue;
            }

            $candidates[] = [
                'depth' => substr_count(trim($normalized, '/'), '/'),
                'index' => $index,
                'source' => $name,
            ];
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => $left['depth'] <=> $right['depth']
        );

        foreach (array_slice($candidates, 0, 80) as $candidate) {
            $header = $zip->getFromIndex((int) $candidate['index'], 16384);

            if (! is_string($header)) {
                continue;
            }

            $name = $this->headerValue($header, 'Plugin Name');

            if ($name === '') {
                continue;
            }

            return [
                'name' => $name,
                'version' => $this->headerValue($header, 'Version'),
                'source' => (string) $candidate['source'],
                'developer' => $this->headerValue($header, 'Author'),
                'productUrl' => $this->firstValidUrl([
                    $this->headerValue($header, 'Plugin URI'),
                    $this->headerValue($header, 'Author URI'),
                ]),
            ];
        }

        return null;
    }

    private function looksLikeTemplateKit(ZipArchive $zip): bool
    {
        $jsonFiles = 0;
        $templateSignals = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(str_replace('\\', '/', $name));

            if (str_ends_with($normalized, '.json')) {
                $jsonFiles++;
            }

            if (
                str_contains($normalized, 'manifest')
                || str_contains($normalized, 'site-settings')
                || str_contains($normalized, 'templates/')
            ) {
                $templateSignals++;
            }
        }

        return $jsonFiles >= 2 && $templateSignals >= 1;
    }

    /**
     * @param list<string> $urls
     */
    private function firstValidUrl(array $urls): string
    {
        foreach ($urls as $url) {
            $url = trim($url);

            if (
                $url !== ''
                && filter_var($url, FILTER_VALIDATE_URL) !== false
            ) {
                return $url;
            }
        }

        return '';
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

    private function failure(): ProductArchiveIdentityResult
    {
        return new ProductArchiveIdentityResult(false, '', '', '', '');
    }
}
