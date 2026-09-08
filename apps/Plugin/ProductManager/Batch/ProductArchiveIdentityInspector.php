<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Batch;

use Throwable;
use ZipArchive;
use WPShop\App\Plugin\ProductManager\CatalogProductType;

final class ProductArchiveIdentityInspector
{
    private const MAX_NESTED_ZIPS = 30;
    private const MAX_NESTED_BYTES = 134217728;

    public function inspect(
        string $path,
        string $filename
    ): ProductArchiveIdentityResult {
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
            $direct = $this->identityFromZip(
                $zip,
                $filename,
                ''
            );

            if ($direct->success) {
                return $direct;
            }

            $nested = $this->nestedIdentity(
                $zip,
                $filename
            );

            if ($nested !== null) {
                return $nested;
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

    private function identityFromZip(
        ZipArchive $zip,
        string $filename,
        string $packageEntry
    ): ProductArchiveIdentityResult {
        $theme = $this->themeIdentity($zip);

        if ($theme !== null) {
            return new ProductArchiveIdentityResult(
                true,
                CatalogProductType::THEME,
                $theme['name'],
                $theme['version'],
                $this->sourceLabel(
                    $packageEntry,
                    $theme['source']
                ),
                $theme['developer'],
                $theme['productUrl'],
                $packageEntry
            );
        }

        $plugin = $this->pluginIdentity($zip);

        if ($plugin !== null) {
            return new ProductArchiveIdentityResult(
                true,
                CatalogProductType::PLUGIN,
                $plugin['name'],
                $plugin['version'],
                $this->sourceLabel(
                    $packageEntry,
                    $plugin['source']
                ),
                $plugin['developer'],
                $plugin['productUrl'],
                $packageEntry
            );
        }

        if ($this->looksLikeTemplateKit($zip)) {
            $name = trim(
                (string) pathinfo(
                    $packageEntry !== ''
                        ? basename($packageEntry)
                        : $filename,
                    PATHINFO_FILENAME
                )
            );

            return new ProductArchiveIdentityResult(
                true,
                CatalogProductType::TEMPLATE_KIT,
                $name,
                '',
                $packageEntry !== ''
                    ? 'nested:' . $packageEntry
                    : 'template-kit-json-structure',
                '',
                '',
                $packageEntry
            );
        }

        return $this->failure();
    }

    private function nestedIdentity(
        ZipArchive $outer,
        string $outerFilename
    ): ?ProductArchiveIdentityResult {
        $candidates = [];
        $seen = 0;

        for (
            $index = 0;
            $index < $outer->numFiles
                && $seen < self::MAX_NESTED_ZIPS;
            ++$index
        ) {
            $entry = $outer->getNameIndex($index);

            if (! is_string($entry)) {
                continue;
            }

            $normalized = strtolower(
                str_replace('\\', '/', $entry)
            );

            if (
                ! str_ends_with($normalized, '.zip')
                || str_starts_with($normalized, '__macosx/')
            ) {
                continue;
            }

            ++$seen;
            $stat = $outer->statIndex($index);

            if (! is_array($stat)) {
                continue;
            }

            $size = max(0, (int) $stat['size']);

            if (
                $size <= 0
                || $size > self::MAX_NESTED_BYTES
            ) {
                continue;
            }

            $temp = tempnam(
                sys_get_temp_dir(),
                'wp-shop-nested-'
            );

            if ($temp === false) {
                continue;
            }

            try {
                if (! $this->extractEntryToFile(
                    $outer,
                    $entry,
                    $temp
                )) {
                    continue;
                }

                $nested = new ZipArchive();

                if ($nested->open($temp) !== true) {
                    continue;
                }

                try {
                    $theme = $this->themeIdentity($nested);

                    if (
                        $theme !== null
                        && ! $theme['isChild']
                    ) {
                        $result = new ProductArchiveIdentityResult(
                            true,
                            CatalogProductType::THEME,
                            $theme['name'],
                            $theme['version'],
                            $this->sourceLabel(
                                $entry,
                                $theme['source']
                            ),
                            $theme['developer'],
                            $theme['productUrl'],
                            $entry
                        );
                        $candidates[] = [
                            'score' => $this->nestedScore(
                                $entry,
                                $outerFilename,
                                $result,
                                false
                            ),
                            'result' => $result,
                        ];

                        continue;
                    }

                    $plugin = $this->pluginIdentity($nested);

                    if ($plugin !== null) {
                        $result = new ProductArchiveIdentityResult(
                            true,
                            CatalogProductType::PLUGIN,
                            $plugin['name'],
                            $plugin['version'],
                            $this->sourceLabel(
                                $entry,
                                $plugin['source']
                            ),
                            $plugin['developer'],
                            $plugin['productUrl'],
                            $entry
                        );
                        $candidates[] = [
                            'score' => $this->nestedScore(
                                $entry,
                                $outerFilename,
                                $result,
                                false
                            ),
                            'result' => $result,
                        ];

                        continue;
                    }

                    if ($this->looksLikeTemplateKit($nested)) {
                        $result = $this->identityFromZip(
                            $nested,
                            basename($entry),
                            $entry
                        );
                        $candidates[] = [
                            'score' => $this->nestedScore(
                                $entry,
                                $outerFilename,
                                $result,
                                false
                            ),
                            'result' => $result,
                        ];
                    }
                } finally {
                    $nested->close();
                }
            } finally {
                @unlink($temp);
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int =>
                $left['score'] <=> $right['score']
        );

        return $candidates[0]['result'];
    }

    private function nestedScore(
        string $entry,
        string $outerFilename,
        ProductArchiveIdentityResult $identity,
        bool $isChild
    ): int {
        $normalized = strtolower(
            str_replace('\\', '/', $entry)
        );
        $basename = strtolower(
            (string) pathinfo(
                basename($entry),
                PATHINFO_FILENAME
            )
        );
        $outer = strtolower(
            (string) pathinfo(
                $outerFilename,
                PATHINFO_FILENAME
            )
        );
        $outer = (string) preg_replace(
            '/(?:[-_ ](?:package|full|download|files|theme|plugin))+$/i',
            '',
            $outer
        );
        $score = substr_count(
            trim($normalized, '/'),
            '/'
        ) * 10;

        if (
            str_contains($normalized, '/plugins/')
            || str_starts_with($normalized, 'plugins/')
            || str_contains($normalized, '/revslider')
        ) {
            $score += 80;
        }

        if (
            $isChild
            || str_contains($basename, 'child')
            || str_contains(
                strtolower($identity->name),
                'child'
            )
        ) {
            $score += 1000;
        }

        if (
            $outer !== ''
            && $basename === $outer
        ) {
            $score -= 150;
        } elseif (
            $outer !== ''
            && str_starts_with($basename, $outer)
        ) {
            $score -= 60;
        }

        return $score;
    }

    private function extractEntryToFile(
        ZipArchive $zip,
        string $entry,
        string $target
    ): bool {
        $stream = $zip->getStream($entry);

        if ($stream === false) {
            return false;
        }

        $output = fopen($target, 'wb');

        if ($output === false) {
            fclose($stream);

            return false;
        }

        try {
            $copied = stream_copy_to_stream(
                $stream,
                $output,
                self::MAX_NESTED_BYTES + 1
            );

            return is_int($copied)
                && $copied > 0
                && $copied <= self::MAX_NESTED_BYTES;
        } finally {
            fclose($stream);
            fclose($output);
        }
    }

    /**
     * @return array{
     *   name:string,
     *   version:string,
     *   source:string,
     *   developer:string,
     *   productUrl:string,
     *   isChild:bool
     * }|null
     */
    private function themeIdentity(ZipArchive $zip): ?array
    {
        $candidates = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(
                str_replace('\\', '/', $name)
            );

            if (
                $normalized === 'style.css'
                || str_ends_with($normalized, '/style.css')
            ) {
                $candidates[] = [
                    'depth' => substr_count(
                        trim($normalized, '/'),
                        '/'
                    ),
                    'index' => $index,
                    'source' => $name,
                ];
            }
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int =>
                $left['depth'] <=> $right['depth']
        );

        foreach ($candidates as $candidate) {
            $header = $zip->getFromIndex(
                (int) $candidate['index'],
                16384
            );

            if (! is_string($header)) {
                continue;
            }

            $name = $this->headerValue(
                $header,
                'Theme Name'
            );

            if ($name === '') {
                continue;
            }

            return [
                'name' => $name,
                'version' => $this->headerValue(
                    $header,
                    'Version'
                ),
                'source' => (string) $candidate['source'],
                'developer' => $this->headerValue(
                    $header,
                    'Author'
                ),
                'productUrl' => $this->firstValidUrl([
                    $this->headerValue(
                        $header,
                        'Theme URI'
                    ),
                    $this->headerValue(
                        $header,
                        'Author URI'
                    ),
                ]),
                'isChild' => $this->headerValue(
                    $header,
                    'Template'
                ) !== '',
            ];
        }

        return null;
    }

    /**
     * @return array{
     *   name:string,
     *   version:string,
     *   source:string,
     *   developer:string,
     *   productUrl:string
     * }|null
     */
    private function pluginIdentity(ZipArchive $zip): ?array
    {
        $candidates = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(
                str_replace('\\', '/', $name)
            );

            if (
                ! str_ends_with($normalized, '.php')
                || str_contains($normalized, '/vendor/')
                || str_contains($normalized, '/node_modules/')
                || str_starts_with($normalized, '__macosx/')
            ) {
                continue;
            }

            $candidates[] = [
                'depth' => substr_count(
                    trim($normalized, '/'),
                    '/'
                ),
                'index' => $index,
                'source' => $name,
            ];
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int =>
                $left['depth'] <=> $right['depth']
        );

        foreach (
            array_slice($candidates, 0, 80)
            as $candidate
        ) {
            $header = $zip->getFromIndex(
                (int) $candidate['index'],
                16384
            );

            if (! is_string($header)) {
                continue;
            }

            $name = $this->headerValue(
                $header,
                'Plugin Name'
            );

            if ($name === '') {
                continue;
            }

            return [
                'name' => $name,
                'version' => $this->headerValue(
                    $header,
                    'Version'
                ),
                'source' => (string) $candidate['source'],
                'developer' => $this->headerValue(
                    $header,
                    'Author'
                ),
                'productUrl' => $this->firstValidUrl([
                    $this->headerValue(
                        $header,
                        'Plugin URI'
                    ),
                    $this->headerValue(
                        $header,
                        'Author URI'
                    ),
                ]),
            ];
        }

        return null;
    }

    private function looksLikeTemplateKit(
        ZipArchive $zip
    ): bool {
        $jsonFiles = 0;
        $templateSignals = 0;

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name)) {
                continue;
            }

            $normalized = strtolower(
                str_replace('\\', '/', $name)
            );

            if (str_ends_with($normalized, '.json')) {
                ++$jsonFiles;
            }

            if (
                str_contains($normalized, 'manifest')
                || str_contains(
                    $normalized,
                    'site-settings'
                )
                || str_contains(
                    $normalized,
                    'templates/'
                )
            ) {
                ++$templateSignals;
            }
        }

        return $jsonFiles >= 2
            && $templateSignals >= 1;
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
                && filter_var(
                    $url,
                    FILTER_VALIDATE_URL
                ) !== false
            ) {
                return $url;
            }
        }

        return '';
    }

    private function headerValue(
        string $content,
        string $field
    ): string {
        $pattern = '/^[ \\t\\/*#@]*'
            . preg_quote($field, '/')
            . '\\s*:\\s*(.+?)\\s*$/mi';

        if (
            preg_match(
                $pattern,
                $content,
                $matches
            ) !== 1
        ) {
            return '';
        }

        $value = trim((string) $matches[1]);
        $value = preg_replace(
            '/\\s*(?:\\*\\/)?\\s*$/',
            '',
            $value
        );

        return is_string($value)
            ? trim($value)
            : '';
    }

    private function sourceLabel(
        string $packageEntry,
        string $source
    ): string {
        return $packageEntry !== ''
            ? 'nested:'
                . $packageEntry
                . ' > '
                . $source
            : $source;
    }

    private function failure(): ProductArchiveIdentityResult
    {
        return new ProductArchiveIdentityResult(
            false,
            '',
            '',
            '',
            ''
        );
    }
}
