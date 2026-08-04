<?php

declare(strict_types=1);

namespace WPShop\Publisher\Manifest;

use JsonException;
use WPShop\Publisher\Contracts\ArtifactManifestDecoratorInterface;
use WPShop\Publisher\Exception\InvalidArtifactManifest;
use WPShop\Publisher\StoredArtifact;

final class JsonArtifactManifestDecorator implements
    ArtifactManifestDecoratorInterface
{
    public function decorate(
        string $manifestJson,
        StoredArtifact $artifact
    ): string {
        $manifest = $this->decodePublisherManifest(
            $manifestJson
        );

        $data = get_object_vars($manifest);

        $data['_artifact'] = [
            'key' => $artifact->storageKey(),
            'filename' => $artifact->filename(),
            'mediaType' => $artifact->mediaType(),
            'size' => $artifact->size(),
            'sha256' => $artifact->sha256(),
        ];

        try {
            return json_encode(
                $data,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw InvalidArtifactManifest::encodingFailed(
                $exception
            );
        }
    }

    private function decodePublisherManifest(
        string $manifestJson
    ): object {
        if (trim($manifestJson) === '') {
            throw InvalidArtifactManifest::emptyManifest();
        }

        try {
            $manifest = json_decode(
                $manifestJson,
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw InvalidArtifactManifest::invalidJson(
                $exception
            );
        }

        if (! is_object($manifest)) {
            throw InvalidArtifactManifest::objectRequired();
        }

        if (property_exists($manifest, '_artifact')) {
            throw InvalidArtifactManifest::reservedArtifactProperty();
        }

        return $manifest;
    }
}
