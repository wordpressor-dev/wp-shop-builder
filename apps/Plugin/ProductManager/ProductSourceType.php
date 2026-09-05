<?php

declare(strict_types=1);

namespace WPShop\App\Plugin\ProductManager;

final class ProductSourceType
{
    public const ENVATO = 'envato';
    public const VENDOR = 'vendor';

    public static function fromSalesPage(string $salesPage): string
    {
        $host = parse_url(trim($salesPage), PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (
            $host === 'themeforest.net'
            || $host === 'codecanyon.net'
        ) {
            return self::ENVATO;
        }

        return self::VENDOR;
    }

    public static function normalize(
        string $sourceType,
        string $salesPage
    ): string {
        $sourceType = strtolower(trim($sourceType));

        if (
            $sourceType === self::ENVATO
            || $sourceType === self::VENDOR
        ) {
            return $sourceType;
        }

        return self::fromSalesPage($salesPage);
    }
}
