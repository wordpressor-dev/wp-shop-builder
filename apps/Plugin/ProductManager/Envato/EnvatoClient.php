<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager\Envato;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WPShop\App\Plugin\ProductManager\Envato\Contracts\EnvatoClientInterface;

final class EnvatoClient implements EnvatoClientInterface
{
    /**
     * @param Closure(string, array<string, string>): array<string, mixed> $getJson
     */
    public function __construct(
        private readonly Closure $getJson,
        private readonly EnvatoItemMapper $mapper = new EnvatoItemMapper()
    ) {
    }

    public function fetch(
        string $itemUrl,
        string $token
    ): EnvatoItem {
        $itemId = $this->itemId($itemUrl);
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException(
                'Envato personal token is empty.'
            );
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'User-Agent' => 'WP-Shop-Builder/ProductManager',
        ];

        $item = ($this->getJson)(
            'https://api.envato.com/v3/market/catalog/item?id=' . $itemId,
            $headers
        );

        $version = [];

        try {
            $version = ($this->getJson)(
                'https://api.envato.com/v3/market/catalog/item-version?id=' . $itemId,
                $headers
            );
        } catch (Throwable) {
            // Item metadata can still contain a usable WordPress version.
        }

        if ($item === []) {
            throw new RuntimeException(
                'Envato item response is empty.'
            );
        }

        return $this->mapper->map(
            $item,
            $version,
            $itemUrl
        );
    }

    private function itemId(string $itemUrl): int
    {
        if (
            preg_match(
                '~/(?:item/[^/]+/)?(\d+)(?:[/?#]|$)~',
                trim($itemUrl),
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Cannot extract Envato item ID from URL.'
            );
        }

        $itemId = (int) $matches[1];

        if ($itemId <= 0) {
            throw new InvalidArgumentException(
                'Cannot extract Envato item ID from URL.'
            );
        }

        return $itemId;
    }
}
