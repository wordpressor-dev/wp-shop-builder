<?php

declare(strict_types=1);

namespace WPShop\Publisher\Validation;

use WPShop\Publisher\Contracts\ThemeHeaderParserInterface;
use WPShop\Publisher\Contracts\ThemePackageValidatorInterface;
use WPShop\Publisher\Exception\ThemeHeaderParsingFailed;
use WPShop\Publisher\Exception\ThemePackageValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\ThemePackageValidation;
use WPShop\Release\Release;

final readonly class WordPressThemePackageValidator implements
    ThemePackageValidatorInterface
{
    private const float SCORE = 100.0;

    public function __construct(
        private ThemeHeaderParserInterface $headerParser
    ) {
    }

    public function validate(
        PackageSource $source,
        Release $release
    ): ThemePackageValidation {
        try {
            $header = $this->headerParser->parse(
                $source->entryPath()
            );
        } catch (ThemeHeaderParsingFailed $exception) {
            throw ThemePackageValidationFailed
                ::invalidHeader(
                    $source->entryPath(),
                    $exception
                );
        }

        if ($header->version() !== $release->version()) {
            throw ThemePackageValidationFailed
                ::versionMismatch(
                    $release->version(),
                    $header->version()
                );
        }

        $template = $header->template();

        if (
            $template !== null
            && ! $this->isSafeThemeSlug($template)
        ) {
            throw ThemePackageValidationFailed
                ::invalidTemplateSlug($template);
        }

        return new ThemePackageValidation(
            $header,
            self::SCORE
        );
    }

    private function isSafeThemeSlug(string $slug): bool
    {
        return strlen($slug) <= 191
            && preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{0,189}[a-z0-9])?$/D',
                $slug
            ) === 1;
    }
}
