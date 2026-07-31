# Version

The Version package exposes immutable information about the versions used by WP Shop Builder.

## Public API

Resolve `WPShop\Version\Contracts\VersionServiceInterface` from the container and call:

```php
$information = $versions->information();
```

The returned `VersionInformation` contains:

- the WP Shop Builder framework version;
- the current PHP version;
- the current WordPress version, or `Unavailable` outside WordPress;
- the WooCommerce version, or `null` when WooCommerce is unavailable.

The package performs no network requests, update checks, or version comparisons.
