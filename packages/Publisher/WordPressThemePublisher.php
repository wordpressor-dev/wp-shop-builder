<?php

declare(strict_types=1);

namespace WPShop\Publisher;

use InvalidArgumentException;
use JsonException;
use WPShop\Blueprint\Blueprint;
use WPShop\Publisher\Contracts\PackageAssemblerInterface;
use WPShop\Publisher\Contracts\PackageSourceResolverInterface;
use WPShop\Publisher\Contracts\PublisherInterface;
use WPShop\Publisher\Contracts\ThemePackageValidatorInterface;
use WPShop\Release\Release;

final readonly class WordPressThemePublisher implements
    PublisherInterface
{
    public const string BLUEPRINT_TYPE = 'theme';

    public function __construct(
        private PackageSourceResolverInterface $sourceResolver,
        private ThemePackageValidatorInterface $packageValidator,
        private PackageAssemblerInterface $packageAssembler
    ) {
    }

    /**
     * @throws JsonException
     */
    public function publish(
        Blueprint $blueprint,
        Release $release
    ): PublicationResult {
        if ($blueprint->type() !== self::BLUEPRINT_TYPE) {
            throw new InvalidArgumentException(
                'WordPress theme publisher requires '
                    . 'Blueprint type "theme".'
            );
        }

        $source = $this->sourceResolver->resolve(
            $blueprint,
            $release
        );

        $validation = $this->packageValidator->validate(
            $source,
            $release
        );

        $artifact = $this->packageAssembler->assemble(
            $blueprint,
            $release,
            $source
        );

        $manifestJson = json_encode(
            [
                'type' => self::BLUEPRINT_TYPE,
                'slug' => $blueprint->slug(),
                'version' => $release->version(),
                'entry' => $source->archiveEntry(),
            ],
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
        );

        return new PublicationResult(
            $manifestJson,
            $validation->score(),
            $artifact
        );
    }
}
