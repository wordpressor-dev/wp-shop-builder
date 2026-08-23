<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Tags;

use InvalidArgumentException;
use WPShop\App\Plugin\ProductManager\Tags\Contracts\CatalogTagRepositoryInterface;

final class ExistingCatalogTagParser
{
    public function __construct(
        private readonly CatalogTagRepositoryInterface $repository
    ) {
    }

    /**
     * @return list<CatalogTag>
     */
    public function parse(string $value): array
    {
        $selected = [];
        $lines = preg_split('/\R/u', $value) ?: [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map(
                'trim',
                explode('|', $line, 2)
            );

            if (
                count($parts) !== 2
                || $parts[0] === ''
                || $parts[1] === ''
            ) {
                throw new InvalidArgumentException(
                    'Invalid tag on line '
                    . ($lineNumber + 1)
                    . '. Expected Name|slug.'
                );
            }

            [$name, $slug] = $parts;

            if (! $this->repository->existsInBoth($name, $slug)) {
                throw new InvalidArgumentException(
                    'Tag is not present in both product_tag and pa_tags: '
                    . $name . '|' . $slug
                );
            }

            $selected[$slug] = new CatalogTag($name, $slug);
        }

        return array_values($selected);
    }
}
