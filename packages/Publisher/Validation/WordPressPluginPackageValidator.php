<?php

declare(strict_types=1);

namespace WPShop\Publisher\Validation;

use WPShop\Publisher\Contracts\PluginHeaderParserInterface;
use WPShop\Publisher\Contracts\PluginPackageValidatorInterface;
use WPShop\Publisher\Exception\PluginHeaderParsingFailed;
use WPShop\Publisher\Exception\PluginPackageValidationFailed;
use WPShop\Publisher\PackageSource;
use WPShop\Publisher\PluginPackageValidation;
use WPShop\Release\Release;

final readonly class WordPressPluginPackageValidator implements
    PluginPackageValidatorInterface
{
    private const float SCORE = 100.0;

    public function __construct(
        private PluginHeaderParserInterface $headerParser
    ) {
    }

    public function validate(
        PackageSource $source,
        Release $release
    ): PluginPackageValidation {
        try {
            $header = $this->headerParser->parse(
                $source->entryPath()
            );
        } catch (PluginHeaderParsingFailed $exception) {
            throw PluginPackageValidationFailed
                ::invalidHeader(
                    $source->entryPath(),
                    $exception
                );
        }

        if ($header->version() !== $release->version()) {
            throw PluginPackageValidationFailed
                ::versionMismatch(
                    $release->version(),
                    $header->version()
                );
        }

        foreach ($header->requiredPlugins() as $slug) {
            if (! $this->isSafePluginSlug($slug)) {
                throw PluginPackageValidationFailed
                    ::invalidRequiredPluginSlug($slug);
            }
        }

        return new PluginPackageValidation(
            $header,
            self::SCORE
        );
    }

    private function isSafePluginSlug(string $slug): bool
    {
        return strlen($slug) <= 191
            && preg_match(
                '/^[a-z0-9](?:[a-z0-9-]{0,189}[a-z0-9])?$/D',
                $slug
            ) === 1;
    }
}
