<?php

declare(strict_types=1);

namespace WPShop\Publisher\Parser;

use WPShop\Publisher\Contracts\PluginHeaderParserInterface;
use WPShop\Publisher\Exception\PluginHeaderParsingFailed;
use WPShop\Publisher\PluginHeader;

final class WordPressPluginHeaderParser implements
    PluginHeaderParserInterface
{
    private const int HEADER_BYTES = 8192;

    private const string HEADER_PATTERN =
        '/^(Plugin Name|Version|Requires at least|Requires PHP|'
        . 'Requires Plugins|Text Domain)[ \t]*:[ \t]*'
        . '(.*?)[ \t]*(?:\*\/)?[ \t]*$/iD';

    public function parse(string $entryPath): PluginHeader
    {
        $contents = $this->readHeader($entryPath);
        $headers = $this->extractHeaders($contents);

        $name = $this->requiredHeader(
            $headers,
            'plugin name',
            'Plugin Name',
            $entryPath
        );

        $version = $this->requiredHeader(
            $headers,
            'version',
            'Version',
            $entryPath
        );

        return new PluginHeader(
            $name,
            $version,
            $this->optionalHeader(
                $headers,
                'requires at least',
                'Requires at least',
                $entryPath
            ),
            $this->optionalHeader(
                $headers,
                'requires php',
                'Requires PHP',
                $entryPath
            ),
            $this->requiredPlugins(
                $headers,
                $entryPath
            ),
            $this->optionalHeader(
                $headers,
                'text domain',
                'Text Domain',
                $entryPath
            )
        );
    }

    private function readHeader(string $entryPath): string
    {
        if (
            ! is_file($entryPath)
            || ! is_readable($entryPath)
        ) {
            throw PluginHeaderParsingFailed
                ::entryFileUnavailable($entryPath);
        }

        $handle = @fopen($entryPath, 'rb');

        if (! is_resource($handle)) {
            throw PluginHeaderParsingFailed
                ::entryFileOpenFailed($entryPath);
        }

        try {
            $contents = @fread(
                $handle,
                self::HEADER_BYTES
            );
        } finally {
            fclose($handle);
        }

        if (! is_string($contents)) {
            throw PluginHeaderParsingFailed
                ::entryFileReadFailed($entryPath);
        }

        return $contents;
    }

    /**
     * @return array<string, string>
     */
    private function extractHeaders(string $contents): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $contents = str_replace(
            ["\r\n", "\r"],
            "\n",
            $contents
        );

        $headers = [];

        foreach (explode("\n", $contents) as $line) {
            $line = ltrim(
                $line,
                " \t/*#@"
            );

            $matched = preg_match(
                self::HEADER_PATTERN,
                $line,
                $matches
            );

            if ($matched !== 1) {
                continue;
            }

            $key = strtolower($matches[1]);

            if (array_key_exists($key, $headers)) {
                continue;
            }

            $headers[$key] = trim($matches[2]);
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function requiredHeader(
        array $headers,
        string $key,
        string $label,
        string $entryPath
    ): string {
        if (! array_key_exists($key, $headers)) {
            throw PluginHeaderParsingFailed
                ::requiredHeaderMissing(
                    $label,
                    $entryPath
                );
        }

        $value = $headers[$key];

        if ($value === '') {
            throw PluginHeaderParsingFailed
                ::requiredHeaderEmpty(
                    $label,
                    $entryPath
                );
        }

        $this->assertValidValue(
            $value,
            $label,
            $entryPath
        );

        return $value;
    }

    /**
     * @param array<string, string> $headers
     */
    private function optionalHeader(
        array $headers,
        string $key,
        string $label,
        string $entryPath
    ): ?string {
        $value = $headers[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $this->assertValidValue(
            $value,
            $label,
            $entryPath
        );

        return $value;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private function requiredPlugins(
        array $headers,
        string $entryPath
    ): array {
        $value = $headers['requires plugins'] ?? null;

        if ($value === null || $value === '') {
            return [];
        }

        $plugins = [];

        foreach (explode(',', $value) as $plugin) {
            $slug = trim($plugin);

            if ($slug === '') {
                continue;
            }

            $this->assertValidValue(
                $slug,
                'Requires Plugins',
                $entryPath
            );

            $plugins[] = $slug;
        }

        return $plugins;
    }

    private function assertValidValue(
        string $value,
        string $header,
        string $entryPath
    ): void {
        if (str_contains($value, "\0")) {
            throw PluginHeaderParsingFailed
                ::invalidHeaderValue(
                    $header,
                    $entryPath
                );
        }
    }
}
