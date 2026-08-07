<?php

declare(strict_types=1);

namespace WPShop\Publisher\Parser;

use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Exception\ThemeHeaderParsingFailed;
use WPShop\Publisher\ThemeHeader;

final class WordPressThemeHeaderParser implements
    ThemeHeaderParserInterface
{
    private const int HEADER_BYTES = 8192;

    private const string HEADER_PATTERN =
        '/^(Theme Name|Version|Tested up to|Requires at least|'
        . 'Requires PHP|Text Domain|Template)[ \t]*:[ \t]*'
        . '(.*?)[ \t]*(?:\*\/)?[ \t]*$/iD';

    public function parse(string $entryPath): ThemeHeader
    {
        $contents = $this->readHeader($entryPath);
        $headers = $this->extractHeaders($contents);

        $name = $this->requiredHeader(
            $headers,
            'theme name',
            'Theme Name',
            $entryPath
        );

        $version = $this->requiredHeader(
            $headers,
            'version',
            'Version',
            $entryPath
        );

        return new ThemeHeader(
            $name,
            $version,
            $this->optionalHeader(
                $headers,
                'tested up to',
                'Tested up to',
                $entryPath
            ),
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
            $this->optionalHeader(
                $headers,
                'text domain',
                'Text Domain',
                $entryPath
            ),
            $this->optionalHeader(
                $headers,
                'template',
                'Template',
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
            throw ThemeHeaderParsingFailed
                ::entryFileUnavailable($entryPath);
        }

        $handle = @fopen($entryPath, 'rb');

        if (! is_resource($handle)) {
            throw ThemeHeaderParsingFailed
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
            throw ThemeHeaderParsingFailed
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
            throw ThemeHeaderParsingFailed
                ::requiredHeaderMissing(
                    $label,
                    $entryPath
                );
        }

        $value = $headers[$key];

        if ($value === '') {
            throw ThemeHeaderParsingFailed
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

    private function assertValidValue(
        string $value,
        string $header,
        string $entryPath
    ): void {
        if (str_contains($value, "\0")) {
            throw ThemeHeaderParsingFailed
                ::invalidHeaderValue(
                    $header,
                    $entryPath
                );
        }
    }
}
